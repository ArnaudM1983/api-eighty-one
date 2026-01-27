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
     * CRUD: List all orders avec Pagination & Filtres
     */
    #[Route('', name: 'api_order_list', methods: ['GET'])]
    public function listOrders(OrderRepository $repo, Request $request): JsonResponse
    {
        $qb = $repo->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        // 1. FILTRE : Recherche (Client ou ID)
        if ($q = $request->query->get('q')) {
            // On cherche dans les infos de shipping jointes
            $qb->leftJoin('o.shippingInfo', 's')
               ->andWhere('
                   o.id LIKE :q OR 
                   s.firstName LIKE :q OR 
                   s.lastName LIKE :q OR
                   s.email LIKE :q
               ')
               ->setParameter('q', '%' . $q . '%');
        }

        // 2. FILTRE : Statut
        if ($status = $request->query->get('status')) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $status);
        }

        // 3. PAGINATION
        // On clone pour compter le total avant d'appliquer la limite
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

        // 4. MAPPING DES DONNÉES
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

        // On renvoie le total dans le header pour Refine
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

        $shipping = $order->getShippingInfo();

        // Calcul TVA Globale (20% incluse dans le TTC)
        $totalTtc = (float)$order->getTotal();
        $totalTax = round($totalTtc - ($totalTtc / 1.2), 2);

        // --- LOGIQUE PAIEMENT BOUTIQUE VS STRIPE ---
        $paymentTypeDisplay = "En attente Stripe";
        if ($order->getStatus() === 'paid' || $order->getStatus() === 'shipped' || $order->getStatus() === 'completed') {
            $paymentTypeDisplay = "Payé par Carte (Stripe)";
        } elseif ($order->getShippingMethod() === 'pickup') {
            $paymentTypeDisplay = "À payer en boutique (Espèces/Comptoir)";
        }

        return $this->json([
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
    }

    /**
     * API: Update Shipping Info
     */
    #[Route('/{id}/shipping', name: 'api_order_update_shipping', methods: ['POST'])]
    public function updateShippingInfo(Order $order, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $method = $data['shippingMethod'] ?? 'pickup';

        $order->setShippingMethod($method);
        $order->setShippingCost($data['shippingCost'] ?? 0);

        if ($method === 'pickup' && $order->getStatus() === 'created') {
            $order->setStatus('created');
        }

        $order->setTotal($order->getTotalPrice());

        // On lie bien les infos de livraison
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
        $this->em->flush(); // On sauve en BDD avant d'envoyer le mail

        return $this->json(['success' => true]);
    }

    /**
     * API: Update Status (Dashboard Admin)
     * Gère : Changement de statut, Paiement comptoir, Remboursement Stripe, Restockage, Envoi Facture.
     */
    #[Route('/{id}/status', name: 'api_order_update_status', methods: ['PATCH', 'PUT'])]
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

        // ==============================================================================
        // 1. GESTION DE L'ANNULATION (Remboursement & Restockage)
        // ==============================================================================
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            
            // A. RESTOCKAGE
            // On remet le stock si la commande était validée (paid/shipped) 
            // OU si c'était un retrait boutique (même en 'created', car le stock est décrémenté à la réservation)
            $wasReserved = in_array($oldStatus, ['paid', 'shipped', 'completed']) 
                           || ($oldStatus === 'created' && $order->getShippingMethod() === 'pickup');

            if ($wasReserved) {
                $stockService->incrementStock($order);
            }

            // B. REMBOURSEMENT STRIPE
            // On cherche s'il y a un paiement Stripe validé
            $stripePayment = $em->getRepository(Payment::class)->findOneBy([
                'order' => $order, 
                'method' => 'stripe',
                'status' => 'success'
            ]);

            if ($stripePayment) {
                try {
                    // On initialise Stripe avec la clé secrète
                    Stripe::setApiKey($this->getParameter('stripe_secret_key'));
                    
                    // On lance le remboursement total
                    $refund = Refund::create([
                        'payment_intent' => $stripePayment->getTransactionId(),
                        'reason' => 'requested_by_customer', // Raison: demande client
                    ]);

                    // On met à jour le statut du paiement en BDD local
                    $stripePayment->setStatus('refunded');
                    $stripePayment->setUpdatedAt(new \DateTimeImmutable());

                } catch (\Exception $e) {
                    // Si le remboursement échoue (ex: trop tard, clé invalide), on bloque et on avertit l'admin
                    return $this->json([
                        'error' => 'Erreur critique lors du remboursement Stripe : ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        // ==============================================================================
        // 2. LOGIQUE PAIEMENT BOUTIQUE (Validation au comptoir)
        // ==============================================================================
        // Si on passe à "Expédié/Retiré" pour un Pickup qui était encore en "Created" (donc non payé en ligne)
        if ($newStatus === 'shipped' && $newStatus !== 'cancelled' && $order->getShippingMethod() === 'pickup' && $oldStatus === 'created') {

            // On annule les éventuelles tentatives de paiement Stripe échouées/en attente
            $existingPayments = $em->getRepository(Payment::class)->findBy(['order' => $order]);
            foreach ($existingPayments as $p) {
                if ($p->getStatus() === 'pending') {
                    $p->setStatus('cancelled');
                    $p->setUpdatedAt(new \DateTimeImmutable());
                }
            }

            // On crée le paiement "Cash/Boutique"
            $payment = new Payment();
            $payment->setOrder($order);
            $payment->setMethod('boutique'); // Méthode spécifique
            $payment->setAmount($order->getTotal());
            $payment->setStatus('success');
            // On génère un ID de transaction fictif pour la traçabilité
            $payment->setTransactionId('CASH-' . $order->getId() . '-' . time());
            $payment->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($payment);
        }

        // ==============================================================================
        // 3. MISE À JOUR FINALE ET SAUVEGARDE
        // ==============================================================================
        $order->setStatus($newStatus);
        $order->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        // ==============================================================================
        // 4. ENVOI DE LA FACTURE PAR EMAIL
        // ==============================================================================
        // On envoie la facture uniquement au passage à 'shipped' (si pas déjà fait)
        if ($newStatus === 'shipped' && $oldStatus !== 'shipped') {
            try {
                // On force le rafraîchissement pour que la facture inclue le nouveau paiement (si Boutique)
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
     * API: Confirmer une commande en retrait boutique (Paiement sur place)
     * Cette route est appelée par le Front quand l'utilisateur clique sur "Valider"
     */
    #[Route('/{id}/confirm-pickup', name: 'api_order_confirm_pickup', methods: ['POST'])]
    public function confirmPickup(Order $order, EntityManagerInterface $em, EmailService $emailService, StockService $stockService): JsonResponse
    {
        // 1. Vérifications de sécurité
        if ($order->getShippingMethod() !== 'pickup') {
            return $this->json(['error' => 'Cette commande n\'est pas configurée pour un retrait boutique'], 400);
        }

        // Si la commande est déjà payée ou expédiée, on ne fait rien
        if (in_array($order->getStatus(), ['paid', 'shipped', 'completed'])) {
            return $this->json(['message' => 'Commande déjà validée'], 200);
        }

        // Décrémente le stock
        $stockService->decrementStock($order);

        // 3. Envoi de l'email
        try {
            // Mail au Client
            $emailService->sendPickupConfirmation($order);
            // Mail à l'admin
            $emailService->sendAdminPickupNotification($order);
        } catch (\Exception $e) {
            // On log l'erreur mais on ne bloque pas la réponse client
            error_log('Erreur envoi mail pickup: ' . $e->getMessage());
        }

        // 4. Sauvegarde (si changement de statut)
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
