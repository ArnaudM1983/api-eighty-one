<?php
namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    #[Route('/api/order/create', name: 'api_order_create', methods: ['POST'])]
    public function createOrder(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $cartToken = $data['cartToken'] ?? null;

        if (!$cartToken) {
            return $this->json(['error' => 'Cart token manquant'], 400);
        }

        // Récupération du panier
        $cart = $em->getRepository(Cart::class)->findOneBy(['token' => $cartToken]);
        if (!$cart) {
            return $this->json(['error' => 'Panier introuvable'], 404);
        }

        // Création de la commande
        $order = new Order();

        // Si utilisateur connecté, on le lie ; sinon user reste null
        if ($this->getUser()) {
            $order->setUser($this->getUser());
        }

        $order->setCartToken($cart->getToken());

        // Création des OrderItems à partir du panier
        foreach ($cart->getItems() as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setVariant($cartItem->getVariant());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setPrice($cartItem->getPrice());
            $order->addItem($orderItem);
        }

        // Calcul et stockage du total
        $order->setTotal($order->getTotal());

        // Création d’un paiement initial en status pending
        $payment = new Payment();
        $payment->setOrder($order);
        $payment->setAmount($order->getTotal());
        $payment->setStatus('pending');
        $payment->setMethod(''); // sera défini plus tard selon le choix du client
        $em->persist($payment);

        // Persist et flush
        $em->persist($order);
        $em->flush();

        // Retour JSON
        return $this->json([
            'success' => true,
            'orderId' => $order->getId(),
            'total' => $order->getTotal()
        ]);
    }
}
