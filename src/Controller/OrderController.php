<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\ShippingInfo;
use App\Entity\OrderItem;
use App\Entity\Cart;
use App\Repository\OrderRepository;
use App\Service\TariffCalculatorService;
use App\Service\MondialRelayService;
use App\Service\ColissimoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TariffCalculatorService $tariffCalculatorService,
        private MondialRelayService $mondialRelayService,
        private ColissimoService $colissimoService
    ) {}

    /**
     * CRUD: List all orders (Dashboard Admin)
     */
    #[Route('', name: 'api_order_list', methods: ['GET'])]
    public function listOrders(OrderRepository $repo): JsonResponse
    {
        $orders = $repo->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(function (Order $o) {
            $shipping = $o->getShippingInfo();
            return [
                'id' => $o->getId(),
                'customer' => $shipping ? ($shipping->getFirstName() . ' ' . $shipping->getLastName()) : 'Anonyme',
                'email' => $shipping ? $shipping->getEmail() : 'N/A',
                'shippingInfo' => [
                    'address' => $shipping?->getAddress(),
                    'city' => $shipping?->getCity(),
                    'postalCode' => $shipping?->getPostalCode(),
                ],
                'total' => $o->getTotal(),
                'status' => $o->getStatus(),
                'createdAt' => $o->getCreatedAt()->format('d/m/Y H:i'),
                'shippingMethod' => $o->getShippingMethod(),
            ];
        }, $orders);

        return $this->json($data);
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

            'items' => array_map(function($item) {
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
     * API: Update Shipping Info (Finalise le choix livraison + Statut Boutique)
     */
    #[Route('/{id}/shipping', name: 'api_order_update_shipping', methods: ['POST'])]
    public function updateShippingInfo(Order $order, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $method = $data['shippingMethod'] ?? 'pickup';

        $order->setShippingMethod($method);
        $order->setShippingCost($data['shippingCost'] ?? 0);
        
        // Si retrait boutique : le statut reste "created" (En attente) pour ton admin
        if ($method === 'pickup' && $order->getStatus() === 'created') {
            $order->setStatus('created');
        }

        $order->setTotal($order->getTotalPrice());

        $shippingInfo = $order->getShippingInfo() ?: new ShippingInfo();
        $shippingInfo->setOrder($order);
        $shippingInfo->setEmail($data['email'] ?? null);
        $shippingInfo->setFirstName($data['firstName'] ?? '');
        $shippingInfo->setLastName($data['lastName'] ?? '');
        $shippingInfo->setAddress($data['address'] ?? '');
        $shippingInfo->setPostalCode($data['postalCode'] ?? '');
        $shippingInfo->setCity($data['city'] ?? '');
        $shippingInfo->setCountry($data['country'] ?? 'FR');

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

        return $this->json(['success' => true]);
    }

    /**
     * API: Update Status (Dashboard Admin)
     */
    #[Route('/{id}/status', name: 'api_order_update_status', methods: ['PATCH', 'PUT'])]
    public function updateStatus(Order $order, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        $allowedStatuses = ['created', 'paid', 'shipped', 'completed', 'cancelled'];
        if (!$newStatus || !in_array($newStatus, $allowedStatuses)) {
            return $this->json(['error' => 'Statut invalide'], 400);
        }

        $order->setStatus($newStatus);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(['success' => true, 'newStatus' => $order->getStatus()]);
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