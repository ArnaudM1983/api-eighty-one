<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\ShippingInfo;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Cart;
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
        private ValidatorInterface $validator
    ) {}

    /**
     * CRUD: Create Order
     * HTTP Method: POST
     * URL: /api/order/create
     * Create an order from cartToken
     */
    #[Route('/api/order/create', name: 'api_order_create', methods: ['POST'])]
    public function createOrder(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $cartToken = $data['cartToken'] ?? null;
        $cart = $em->getRepository(Cart::class)->findOneBy(['token' => $cartToken]);

        // Création de la commande
        $order = new Order();

        // Création des OrderItems à partir du panier
        foreach ($cart->getItems() as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setVariant($cartItem->getVariant());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($cartItem->getPrice());

            // Stocker le poids unitaire dans l'item
            $orderItem->setWeight($cartItem->getWeight());

            $order->addItem($orderItem);
        }

        // Calcul et stockage du total
        $order->setTotal($order->getTotal());

        // Stocker le poids total dans la commande
        $order->setTotalWeight($cart->getTotalWeight());

        // Création d’un paiement initial en status pending
        $payment = new Payment();
        $payment->setOrder($order);
        $payment->setAmount($order->getTotal());
        $payment->setStatus('pending');
        $payment->setMethod('');
        $em->persist($payment);

        // Persist et flush
        $em->persist($order);
        $em->flush();

        // Retour JSON
        return $this->json([
            'success' => true,
            'orderId' => $order->getId(),
            'total' => $order->getTotal(),
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
     * API: Update or Create ShippingInfo for an Order
     * HTTP Method: POST
     * URL: /api/order/{id}/shipping
     */
    #[Route('/api/order/{id}/shipping', name: 'api_order_update_shipping', methods: ['POST'])]
    public function updateShippingInfo(Order $order, Request $request): JsonResponse
    {
        try {
            $shippingInfo = $order->getShippingInfo();

            if (!$shippingInfo) {
                $shippingInfo = new ShippingInfo();
                $shippingInfo->setOrder($order);
            }

            // 1. Désérialisation (met à jour l'objet avec les données JSON)
            $this->serializer->deserialize(
                $request->getContent(),
                ShippingInfo::class,
                'json',
                ['object_to_populate' => $shippingInfo]
            );

            $errors = $this->validator->validate($shippingInfo);

            if (count($errors) > 0) {
                // Si des erreurs de validation existent, formater et retourner 400
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json([
                    'error' => 'Erreur de validation des informations de livraison.',
                    'details' => $errorMessages // Retourne un tableau 'champ' => 'message'
                ], 400); // Bad Request
            }

            // 3. Persister et Enregistrer (seulement si la validation a réussi)
            $this->em->persist($shippingInfo);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Informations de livraison enregistrées avec succès.',
                'shippingInfoId' => $shippingInfo->getId()
            ], 200);
        } catch (\Exception $e) {
            // Gérer les erreurs inattendues (JSON mal formé, etc.)
            return $this->json([
                'error' => 'Erreur inattendue.',
                'details' => $e->getMessage()
            ], 400);
        }
    }
}
