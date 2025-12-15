<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\ShippingInfo;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Cart;
use App\Service\TariffCalculatorService;
use App\Service\MondialRelayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/order')]
class OrderController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private TariffCalculatorService $tariffCalculatorService,
        private MondialRelayService $mondialRelayService
    ) {}

    /**
     * CRUD: Create Order
     * HTTP Method: POST
     * URL: /api/order/create
     */
    #[Route('/create', name: 'api_order_create', methods: ['POST'])]
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

        try {
            // Création des OrderItems à partir du panier (Items, Poids unitaire)
            foreach ($cart->getItems() as $cartItem) {
                // Vérification de sécurité critique: l'article est-il toujours valide?
                if (!$cartItem->getProduct() || $cartItem->getQuantity() <= 0) {
                    continue; // Ignore les items orphelins ou invalides
                }

                $orderItem = new OrderItem();
                $orderItem->setProduct($cartItem->getProduct());
                $orderItem->setVariant($cartItem->getVariant());
                $orderItem->setQuantity($cartItem->getQuantity());
                
                // Assurez-vous que le prix et le poids ne sont pas nulls ici si la DB l'exige
                $orderItem->setPrice($cartItem->getPrice() ?? '0.00'); 
                $orderItem->setWeight($cartItem->getWeight() ?? 0.0); 
                
                $order->addItem($orderItem);
            }

            // Si la commande n'a finalement aucun article valide
            if ($order->getItems()->isEmpty()) {
                 return $this->json(['error' => 'Le panier ne contient aucun article valide pour la commande.'], 400);
            }

            // Stock le poids total
            $order->setTotalWeight($cart->getTotalWeight() ?? 0.0);

            // Initialiser le champ $total avec le sous-total
            $order->setTotal($order->getSubTotal()); 

            $em->persist($order);
            $em->flush();

            // Retour JSON de succès
            return $this->json([
                'success' => true,
                'message' => 'Commande initiale créée',
                'orderId' => $order->getId(),
                'subTotal' => $order->getSubTotal(),
                'total' => $order->getTotal(),
                'totalWeight' => $order->getTotalWeight()
            ]);

        } catch (\Exception $e) {
            // --- CATCHER L'ERREUR DE PERSISTANCE ET RENVOYER LE DÉTAIL ---
            return $this->json([
                'error' => 'Erreur serveur lors de la persistance de la commande.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500); 
        }
    }

    /**
     * CRUD: Delete an order
     * HTTP Method: DELETE
     * URL: /api/order/{id}
     */
    #[Route('/{id}', name: 'api_order_delete', methods: ['DELETE'])]
    public function deleteOrder(Order $order): JsonResponse
    {
        // Contrôle de sécurité: Interdire la suppression si la commande est payée
        if ($order->getStatus() !== 'created' && $order->getStatus() !== 'cancelled') {
             
             return $this->json(['error' => 'Impossible de supprimer cette commande. Statut actuel: ' . $order->getStatus()], 403);
        }

        try {
            
            $this->em->remove($order);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => "La commande #{$order->getId()} a été supprimée avec succès."
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur inattendue lors de la suppression de la commande.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * CRUD: Get an order
     * HTTP Method: GET
     * URL: /api/order/{id}
     * Get an order by id
     */
    #[Route('/{id}', name: 'api_order_get', methods: ['GET'])]
    public function getOrder(int $id, EntityManagerInterface $em): JsonResponse
    {
        $order = $em->getRepository(Order::class)->find($id);

        if (!$order) {
            return $this->json(['error' => 'Commande introuvable'], 404);
        }

        // Création du tableau des items de la commande (OrderItem)
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
                // 'totalWeight' a été retiré car il est sur Order, pas OrderItem
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
            
            // --- AJOUT DE LA LISTE DES ARTICLES ---
            'items' => $items, 
            
            'total' => $order->getTotal(),
            'totalWeight' => $order->getTotalWeight(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
            
            'payments' => $payments,
        ]);
    }

    /**
     * API: Calculate Shipping Tariff
     * HTTP Method: POST
     * URL: /api/shipping/calculate
     */
    #[Route('/shipping/calculate', name: 'api_shipping_calculate', methods: ['POST'])]
    public function calculateShipping(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $weightInKg = $data['totalWeight'] ?? 0.0;
        $modeCode = $data['modeCode'] ?? null; 
        $countryCode = $data['countryCode'] ?? 'FR'; 

        if (empty($modeCode) || $weightInKg <= 0) {
            return $this->json(['error' => 'Poids ou mode de livraison manquant.'], 400);
        }

        try {
            $shippingCost = $this->tariffCalculatorService->calculateShippingCost(
                (float) $weightInKg,
                $modeCode,
                $countryCode
            );

            return $this->json([
                'success' => true,
                'shippingCost' => number_format($shippingCost, 2, '.', ''),
                'message' => 'Tarif calculé avec succès.'
            ]);
        } catch (\Exception $e) {
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
     */
    #[Route('/{id}/shipping', name: 'api_order_update_shipping', methods: ['POST'])]
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

    /**
     * API: Search Mondial Relay PUDOs
     * HTTP Method: POST
     * URL: /api/order/pudo/search
     */
    #[Route('/pudo/search', name: 'api_order_pudo_search', methods: ['POST'])]
    public function searchPudos(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Idéalement, récupérer ces valeurs du ShippingInfo si elles existent déjà
        $postalCode = $data['postalCode'] ?? null;
        $countryCode = $data['countryCode'] ?? 'FR';
        $weightInKg = $data['totalWeight'] ?? 0.0; // Poids en KG

        if (empty($postalCode) || $weightInKg <= 0) {
            return $this->json(['error' => 'Code postal ou poids manquant.'], 400);
        }

        try {
            // Le service gère la conversion KG -> Grammes et l'appel SOAP
            $pudos = $this->mondialRelayService->searchPointsRelais(
                $postalCode,
                $countryCode,
                (float) $weightInKg
            );

            return $this->json([
                'success' => true,
                'pudos' => $pudos,
                'message' => count($pudos) . ' Points Relais trouvés.'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la recherche des Points Relais.',
                'details' => $e->getMessage()
            ], 400);
        }
    }
}