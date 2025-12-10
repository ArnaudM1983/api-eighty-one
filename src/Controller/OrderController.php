<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\ShippingInfo;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Cart;
use App\Service\TariffCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrderController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private TariffCalculatorService $tariffCalculatorService
    ) {}

    /**
     * CRUD: Create Order
     * HTTP Method: POST
     * URL: /api/order/create
     * Crée une commande initiale à partir du panier (cartToken).
     *
     * NOTE: Cette étape crée l'objet Order avec les articles et le poids. 
     * Le total final, les frais de port et le paiement sont gérés plus tard.
     */
    #[Route('/api/order/create', name: 'api_order_create', methods: ['POST'])]
    public function createOrder(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $cartToken = $data['cartToken'] ?? null;
        $cart = $em->getRepository(Cart::class)->findOneBy(['token' => $cartToken]);

        if (!$cart) {
            return $this->json(['error' => 'Panier introuvable'], 404);
        }

        $order = new Order();
        $order->setCartToken($cartToken);
        $order->setStatus('created');

        // Création des OrderItems à partir du panier (Items, Poids unitaire)
        foreach ($cart->getItems() as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setVariant($cartItem->getVariant());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($cartItem->getPrice());
            $orderItem->setWeight($cartItem->getWeight());
            $order->addItem($orderItem);
        }

        // Stock le poids total et le sous-total des articles
        $order->setTotalWeight($cart->getTotalWeight());
        $order->setTotal('0.00');

        // Persist
        $em->persist($order);
        $em->flush();

        // Retour JSON 
        return $this->json([
            'success' => true,
            'message' => 'Commande initiale créée (sans frais de port ni paiement)',
            'orderId' => $order->getId(),
            'subTotal' => $order->getSubTotal(),
            'totalWeight' => $order->getTotalWeight()
        ]);
    }


    /**
     * CRUD: Get an order
     * HTTP Method: GET
     * URL: /api/order/{id}
     * Get an order by id
     */
    #[Route('/api/order/{id}', name: 'api_order_get', methods: ['GET'])]
    public function getOrder(int $id, EntityManagerInterface $em): JsonResponse
    {
        $order = $em->getRepository(Order::class)->find($id);

        if (!$order) {
            return $this->json(['error' => 'Commande introuvable'], 404);
        }

        $items = array_map(function ($item) {
            return [
                'orderItemId' => $item->getId(),
                'productId' => $item->getProduct()?->getId(),
                'variantId' => $item->getVariant()?->getId(),
                'name' => $item->getProduct()?->getName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
                'total' => $item->getTotalPrice(),
                'weight' => $item->getWeight(),
                'totalWeight' => $item->getTotalWeight(),
            ];
        }, $order->getItems()->toArray());

        $payments = array_map(function ($payment) {
            return [
                'paymentId' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'status' => $payment->getStatus(),
                'method' => $payment->getMethod(),
                'transactionId' => $payment->getTransactionId(),
            ];
        }, $order->getPayments()->toArray());

        return $this->json([
            'orderId' => $order->getId(),
            'cartToken' => $order->getCartToken(),
            'total' => $order->getTotal(),
            'totalWeight' => $order->getTotalWeight(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
            'items' => $items,
            'payments' => $payments,
        ]);
    }

    /**
     * API: Calculate Shipping Tariff
     * HTTP Method: POST
     * URL: /api/shipping/calculate
     * Calcule le coût de livraison TTC basé sur le poids et les options de livraison fournies par le client.
     */
    #[Route('/api/shipping/calculate', name: 'api_shipping_calculate', methods: ['POST'])]
    public function calculateShipping(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Récupérer les données nécessaires du frontend
        $weightInKg = $data['totalWeight'] ?? 0.0;
        $modeCode = $data['modeCode'] ?? null; // Ex: 'pr', 'locker', 'colissimo_standard'
        $countryCode = $data['countryCode'] ?? 'FR'; // Pays de destination
        
        if (empty($modeCode) || $weightInKg <= 0) {
             return $this->json(['error' => 'Poids ou mode de livraison manquant.'], 400);
        }

        try {
            // Appel au service de calcul de tarifs
            $shippingCost = $this->tariffCalculatorService->calculateShippingCost(
                (float) $weightInKg,
                $modeCode,
                $countryCode
            );

            // Retourner le coût TTC au frontend
            return $this->json([
                'success' => true,
                'shippingCost' => number_format($shippingCost, 2, '.', ''), // Formaté pour être décimal
                'message' => 'Tarif calculé avec succès.'
            ]);

        } catch (\Exception $e) {
            // Gérer les erreurs de tarif non trouvé (ex: poids trop lourd ou mode invalide)
            return $this->json([
                'error' => 'Impossible de calculer le tarif.',
                'details' => $e->getMessage()
            ], 400);
        }
    }


    /**
     * API: Update Shipping Info, Method, Cost, PUDO details, and Finalize Total.
     * HTTP Method: POST
     * URL: /api/order/{id}/shipping
     * * NOTE: Cette route gère à la fois l'adresse de livraison et la tarification.
     * Le JSON entrant doit contenir les champs d'adresse/PUDO ET les champs shippingMethod/shippingCost.
     */
    #[Route('/api/order/{id}/shipping', name: 'api_order_update_shipping', methods: ['POST'])]
    public function updateShippingInfo(Order $order, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Mise à jour des frais et de la méthode dans l'entité Order
        $shippingMethod = $data['shippingMethod'] ?? null;
        $shippingCost = $data['shippingCost'] ?? '0.00';

        if (empty($shippingMethod)) {
            return $this->json(['error' => 'La méthode de livraison est manquante.'], 400);
        }

        $order->setShippingMethod($shippingMethod);
        $order->setShippingCost($shippingCost);

        // Recalculer et stocker le total final (SubTotal + ShippingCost)
        $order->setTotal($order->getTotalPrice());

        // Mise à jour ou Création de ShippingInfo
        try {
            $shippingInfo = $order->getShippingInfo() ?: new ShippingInfo();
            $shippingInfo->setOrder($order);

            // Mis à jour l'objet ShippingInfo avec toutes les données
            $this->serializer->deserialize(
                $request->getContent(),
                ShippingInfo::class,
                'json',
                ['object_to_populate' => $shippingInfo]
            );

            $errors = $this->validator->validate($shippingInfo);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                return $this->json([
                    'error' => 'Erreur de validation des informations de livraison.',
                    'details' => $errorMessages
                ], 400);
            }

            $this->em->persist($shippingInfo);

            // Création d'un paiement 'pending' avec le bon total
            if ($order->getPayments()->isEmpty()) {
                $payment = new Payment();
                $payment->setOrder($order);
                $payment->setAmount($order->getTotalPrice()); 
                $payment->setStatus('pending');
                $payment->setMethod(''); 
                $this->em->persist($payment);
            }

            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Informations de livraison et frais enregistrés.',
                'totalFinal' => $order->getTotal(),
                'shippingInfoId' => $shippingInfo->getId()
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur inattendue lors de la mise à jour.',
                'details' => $e->getMessage()
            ], 400);
        }
    }
}
