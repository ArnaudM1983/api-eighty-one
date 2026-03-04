<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\ShippingInfo;
use App\Entity\OrderItem;
use App\Entity\Cart;
use App\Entity\Payment;
use App\Repository\OrderRepository;
use App\Service\EmailService;
use App\Service\TariffCalculatorService;
use App\Service\MondialRelayService;
use App\Service\ColissimoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\StockService;
use Stripe\Stripe;            
use Stripe\Refund;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TariffCalculatorService $tariffCalculatorService,
        private MondialRelayService $mondialRelayService,
        private ColissimoService $colissimoService,
        private EmailService $emailService
    ) {}

    /**
     * SÉCURITÉ INTERNE : Fonction réutilisable pour vérifier si l'utilisateur a le droit de voir/modifier la commande
     */
    private function checkOrderAccess(Order $order, Request $request, array $data = []): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true; // L'admin a toujours le droit
        }

        $providedToken = $data['cartToken'] 
            ?? $request->query->get('cartToken') 
            ?? $request->cookies->get('cartToken') 
            ?? $request->cookies->get('cart_token');

        // Nettoyage des fausses valeurs JS
        if ($providedToken === 'null' || $providedToken === 'undefined') {
            $providedToken = null;
        }

        $dbToken = $order->getCartToken();

        // Le token DOIT exister en base, être fourni par l'utilisateur, et correspondre parfaitement
        return !empty($dbToken) && !empty($providedToken) && $dbToken === $providedToken;
    }

    /**
     * CRUD: List all orders avec Pagination & Filtres
     */
    #[Route('', name: 'api_order_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listOrders(OrderRepository $repo, Request $request): JsonResponse
    {
        $qb = $repo->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        if ($q = $request->query->get('q')) {
            $qb->leftJoin('o.shippingInfo', 's')
               ->andWhere('
                   o.id LIKE :q OR 
                   s.firstName LIKE :q OR 
                   s.lastName LIKE :q OR
                   s.email LIKE :q
               ')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($status = $request->query->get('status')) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $status);
        }

        $countQb = clone $qb;
        $total = $countQb->select('count(o.id)')->getQuery()->getSingleScalarResult();

        $page = (int) $request->query->get('_page', 1);
        $limit = (int) $request->query->get('_limit', 20);
        $offset = ($page - 1) * $limit;

        $orders = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Order $o) {
            $shipping = $o->getShippingInfo();
            return [
                'id' => $o->getId(),
                'customer' => $shipping ? ($shipping->getFirstName() . ' ' . $shipping->getLastName()) : 'Anonyme',
                'email' => $shipping ? $shipping->getEmail() : 'N/A',
                'total' => $o->getTotal(),
                'status' => $o->getStatus(),
                'createdAt' => $o->getCreatedAt()->format('d/m/Y H:i'),
                'shippingMethod' => $o->getShippingMethod(),
            ];
        }, $orders);

        return $this->json($data, 200, [
            'x-total-count' => $total,
            'Access-Control-Expose-Headers' => 'x-total-count'
        ]);
    }

    /**
     * CRUD: Get order detail (Admin & Front Client)
     */
    #[Route('/{id}', name: 'api_order_get', methods: ['GET'])]
    public function getOrder(int $id, OrderRepository $repo, Request $request): JsonResponse
    {
        $order = $repo->find($id);

        if (!$order) {
            return $this->json(['error' => 'Commande introuvable'], 404);
        }

        // --- SÉCURITÉ ---
        if (!$this->checkOrderAccess($order, $request)) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        $shipping = $order->getShippingInfo();
        $totalTtc = (float)$order->getTotal();
        $totalTax = round($totalTtc - ($totalTtc / 1.2), 2);

        $paymentTypeDisplay = "En attente Stripe";
        if ($order->getStatus() === 'paid' || $order->getStatus() === 'shipped' || $order->getStatus() === 'completed') {
            $paymentTypeDisplay = "Payé par Carte (Stripe)";
        } elseif ($order->getShippingMethod() === 'pickup') {
            $paymentTypeDisplay = "À payer en boutique (Espèces/Comptoir)";
        }

        $response = $this->json([
            'id' => $order->getId(),
            'orderId' => $order->getId(),
            'status' => $order->getStatus(),
            'total' => $order->getTotal(),
            'totalTax' => $totalTax,
            'totalWeight' => $order->getTotalWeight(),
            'createdAt' => $order->getCreatedAt()->format('d/m/Y H:i'),
            'shippingMethod' => $order->getShippingMethod(),
            'shippingCost' => $order->getShippingCost(),
            'customerIp' => $request->getClientIp() ?? 'Non détectée',
            'paymentTypeDisplay' => $paymentTypeDisplay,

            'customer' => $shipping ? ($shipping->getFirstName() . ' ' . $shipping->getLastName()) : 'Anonyme',
            'shippingInfo' => [
                'firstName' => $shipping?->getFirstName(),
                'lastName' => $shipping?->getLastName(),
                'email' => $shipping?->getEmail(),
                'phone' => $shipping?->getPhone() ?? 'N/A',
                'address' => $shipping?->getAddress(),
                'city' => $shipping?->getCity(),
                'postalCode' => $shipping?->getPostalCode(),
                'country' => $shipping?->getCountry() ?? 'FR',
                'pudoId' => $shipping?->getPudoId(),
                'pudoName' => $shipping?->getPudoName(),
                'pudoAddress' => $shipping?->getPudoAddress(),
                'pudoPostalCode' => $shipping?->getPudoPostalCode(),
                'pudoCity' => $shipping?->getPudoCity(),
            ],

            'items' => array_map(function ($item) {
                $lineTotalTtc = (float)$item->getTotalPrice();
                return [
                    'orderItemId' => $item->getId(),
                    'name' => $item->getProduct()?->getName(),
                    'variantName' => $item->getVariant()?->getName(),
                    'sku' => $item->getVariant() ? $item->getVariant()->getSku() : ($item->getProduct() ? $item->getProduct()->getSku() : 'N/A'),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                    'total' => $item->getTotalPrice(),
                    'taxAmount' => round($lineTotalTtc - ($lineTotalTtc / 1.2), 2),
                ];
            }, $order->getItems()->toArray()),

            'payments' => array_map(fn($p) => [
                'paymentId' => $p->getId(),
                'transactionId' => $p->getTransactionId(),
                'status' => $p->getStatus(),
                'amount' => $p->getAmount(),
                'method' => $p->getMethod() ?? 'Carte Bancaire',
            ], $order->getPayments()->toArray()),
        ]);

        // --- ANTI-CACHE POUR PROTÉGER LA DONNÉE ---
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        
        return $response;
    }

    /**
     * API: Update Shipping Info
     */
    #[Route('/{id}/shipping', name: 'api_order_update_shipping', methods: ['POST'])]
    public function updateShippingInfo(Order $order, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // --- SÉCURITÉ ---
        if (!$this->checkOrderAccess($order, $request, $data)) {
            return $this->json(['error' => 'Action non autorisée'], 403);
        }

        $method = $data['shippingMethod'] ?? 'pickup';

        $order->setShippingMethod($method);
        $order->setShippingCost($data['shippingCost'] ?? 0);

        if ($method === 'pickup' && $order->getStatus() === 'created') {
            $order->setStatus('created');
        }

        $order->setTotal($order->calculateTotal());

        $shippingInfo = $order->getShippingInfo() ?: new ShippingInfo();
        $shippingInfo->setOrder($order);
        $shippingInfo->setEmail($data['email'] ?? null);
        $shippingInfo->setFirstName($data['firstName'] ?? '');
        $shippingInfo->setLastName($data['lastName'] ?? '');
        $shippingInfo->setAddress($data['address'] ?? '');
        $shippingInfo->setPostalCode($data['postalCode'] ?? '');
        $shippingInfo->setCity($data['city'] ?? '');
        $shippingInfo->setCountry($data['country'] ?? 'FR');
        $shippingInfo->setPhone($data['phone'] ?? null);

        if (isset($data['pudoId'])) {
            $shippingInfo->setPudoId($data['pudoId']);
            $shippingInfo->setPudoName($data['pudoName']);
            $shippingInfo->setPudoAddress($data['pudoAddress']);
            $shippingInfo->setPudoPostalCode($data['pudoPostalCode']);
            $shippingInfo->setPudoCity($data['pudoCity']);
        } else {
            $shippingInfo->setPudoId(null);
        }

        $this->em->persist($shippingInfo);
        $this->em->flush();

        return $this->json(['success' => true, 'newTotal' => $order->getTotal()]);
    }

    /**
     * API: Update Status (Dashboard Admin)
     */
    #[Route('/{id}/status', name: 'api_order_update_status', methods: ['PATCH', 'PUT', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(
        Order $order, 
        Request $request, 
        EntityManagerInterface $em, 
        StockService $stockService 
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;
        $oldStatus = $order->getStatus();

        $allowedStatuses = ['created', 'paid', 'shipped', 'completed', 'cancelled'];
        if (!$newStatus || !in_array($newStatus, $allowedStatuses)) {
            return $this->json(['error' => 'Statut invalide'], 400);
        }

        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            
            $wasReserved = in_array($oldStatus, ['paid', 'shipped', 'completed']) 
                           || ($oldStatus === 'created' && $order->getShippingMethod() === 'pickup');

            if ($wasReserved) {
                $stockService->incrementStock($order);
            }

            $stripePayment = $em->getRepository(Payment::class)->findOneBy([
                'order' => $order, 
                'method' => 'stripe',
                'status' => 'success'
            ]);

            if ($stripePayment) {
                try {
                    Stripe::setApiKey($this->getParameter('stripe_secret_key'));
                    
                    Refund::create([
                        'payment_intent' => $stripePayment->getTransactionId(),
                        'reason' => 'requested_by_customer',
                    ]);

                    $stripePayment->setStatus('refunded');
                    $stripePayment->setUpdatedAt(new \DateTimeImmutable());

                } catch (\Exception $e) {
                    return $this->json([
                        'error' => 'Erreur critique lors du remboursement Stripe : ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        if ($newStatus === 'shipped' && $newStatus !== 'cancelled' && $order->getShippingMethod() === 'pickup' && $oldStatus === 'created') {
            $existingPayments = $em->getRepository(Payment::class)->findBy(['order' => $order]);
            foreach ($existingPayments as $p) {
                if ($p->getStatus() === 'pending') {
                    $p->setStatus('cancelled');
                    $p->setUpdatedAt(new \DateTimeImmutable());
                }
            }

            $payment = new Payment();
            $payment->setOrder($order);
            $payment->setMethod('boutique');
            $payment->setAmount($order->getTotal());
            $payment->setStatus('success');
            $payment->setTransactionId('CASH-' . $order->getId() . '-' . time());
            $payment->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($payment);
        }

        $order->setStatus($newStatus);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        if ($newStatus === 'shipped' && $oldStatus !== 'shipped') {
            try {
                $em->refresh($order);
                $this->emailService->sendInvoiceNotification($order);
            } catch (\Exception $e) {
                error_log("Erreur envoi facture commande #" . $order->getId() . " : " . $e->getMessage());
            }
        }

        return $this->json([
            'success' => true,
            'newStatus' => $order->getStatus(),
            'message' => $newStatus === 'cancelled' 
                ? 'Commande annulée. Stock rétabli et remboursement effectué (si éligible).' 
                : 'Statut mis à jour avec succès.'
        ]);
    }

    /**
     * CRUD: Create Order
     */
    #[Route('/create', name: 'api_order_create', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $cartToken = $data['cartToken'] ?? null;

        if (empty($cartToken) || $cartToken === 'null' || $cartToken === 'undefined') {
            return $this->json(['error' => 'Token invalide'], 400);
        }

        $cart = $this->em->getRepository(Cart::class)->findOneBy(['token' => $cartToken]);

        if (!$cart) return $this->json(['error' => 'Panier introuvable'], 404);

        $order = new Order();
        $order->setCartToken($cartToken);
        $order->setStatus('created');

        foreach ($cart->getItems() as $cartItem) {
            if (!$cartItem->getProduct() || $cartItem->getQuantity() <= 0) continue;

            $orderItem = new OrderItem();
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setVariant($cartItem->getVariant());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($cartItem->getPrice() ?? '0.00');
            $orderItem->setWeight($cartItem->getWeight() ?? 0.0);
            $order->addItem($orderItem);
        }

        if ($order->getItems()->isEmpty()) return $this->json(['error' => 'Panier vide'], 400);

        $order->setTotalWeight($cart->getTotalWeight() ?? 0.0);
        $order->setTotal($order->getSubTotal());

        $this->em->persist($order);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'orderId' => $order->getId(),
            'subTotal' => $order->getSubTotal(),
            'total' => $order->getTotal(),
        ]);
    }

    /**
     * API: Confirmer une commande en retrait boutique
     */
    #[Route('/{id}/confirm-pickup', name: 'api_order_confirm_pickup', methods: ['POST'])]
    public function confirmPickup(Order $order, Request $request, EntityManagerInterface $em, EmailService $emailService, StockService $stockService): JsonResponse
    {
        // --- SÉCURITÉ ---
        if (!$this->checkOrderAccess($order, $request)) {
            return $this->json(['error' => 'Action non autorisée'], 403);
        }

        if ($order->getShippingMethod() !== 'pickup') {
            return $this->json(['error' => 'Cette commande n\'est pas configurée pour un retrait boutique'], 400);
        }

        if (in_array($order->getStatus(), ['paid', 'shipped', 'completed'])) {
            return $this->json(['message' => 'Commande déjà validée'], 200);
        }

        $stockService->decrementStock($order);

        try {
            $emailService->sendPickupConfirmation($order);
            $emailService->sendAdminPickupNotification($order);
        } catch (\Exception $e) {
            error_log('Erreur envoi mail pickup: ' . $e->getMessage());
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Commande validée, email envoyé',
            'orderId' => $order->getId()
        ]);
    }

    /**
     * API: Calculate Shipping
     */
    #[Route('/shipping/calculate', name: 'api_shipping_calculate', methods: ['POST'])]
    public function calculateShipping(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $weightInKg = (float)($data['totalWeight'] ?? 0.0);
        $modeCode = $data['modeCode'] ?? null;
        $countryCode = $data['countryCode'] ?? 'FR';

        try {
            $cost = $this->tariffCalculatorService->calculateShippingCost($weightInKg, $modeCode, $countryCode);
            return $this->json(['success' => true, 'shippingCost' => number_format($cost, 2, '.', '')]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * API: Search Mondial Relay PUDOs
     */
    #[Route('/pudo/search', name: 'api_order_pudo_search', methods: ['POST'])]
    public function searchPudos(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $postalCode = $data['postalCode'] ?? null;
        $countryCode = $data['countryCode'] ?? 'FR';
        $weightInKg = (float)($data['totalWeight'] ?? 0.0);

        try {
            $pudos = $this->mondialRelayService->searchPointsRelais($postalCode, $countryCode, $weightInKg);
            return $this->json(['success' => true, 'pudos' => $pudos]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * API: Search Colissimo PUDOs
     */
    #[Route('/pudo/colissimo/search', name: 'api_order_colissimo_search', methods: ['POST'])]
    public function searchColissimoPudos(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $address = $data['address'] ?? '';
        $zipCode = $data['postalCode'] ?? $data['zipCode'] ?? null;
        $city = $data['city'] ?? null;
        $countryCode = $data['countryCode'] ?? 'FR';
        $weightInKg = (float)($data['totalWeight'] ?? 0.0);

        try {
            $pudos = $this->colissimoService->searchPointsRetrait($address, $zipCode, $city, $countryCode, $weightInKg);
            return $this->json(['success' => true, 'pudos' => $pudos, 'count' => count($pudos)]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}