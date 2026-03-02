<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Payment;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Service\StockService;

#[Route('/api/payment')]
class StripeController extends AbstractController
{
    #[Route('/stripe/create-intent/{id}', name: 'api_stripe_intent', methods: ['POST'])]
    public function createIntent(Request $request, Order $order, EntityManagerInterface $em): JsonResponse
    {
        // 1. On récupère les données de livraison envoyées par le front
        $data = json_decode($request->getContent(), true) ?? [];

        // 2. CORRECTION CRITIQUE : Mise à jour immédiate de la commande
        if (isset($data['shippingCost'])) {
            $order->setShippingCost((float)$data['shippingCost']);
            if (isset($data['shippingMethod'])) {
                $order->setShippingMethod($data['shippingMethod']);
            }
            $em->flush(); // Sauvegarde en base avant le calcul Stripe
        }

        Stripe::setApiKey($this->getParameter('stripe_secret_key'));
        
        // 3. Calcul garanti avec les frais de port
        $total = $order->calculateTotal();
        $order->setTotal($total);

        $existingPayment = $em->getRepository(Payment::class)->findOneBy(['order' => $order]);

        if ($existingPayment && $existingPayment->getStatus() === 'pending') {
            if ($existingPayment->getMethod() !== 'stripe') {
                $em->remove($existingPayment);
                $em->flush();
                $existingPayment = null; 
            }
        }

        $payment = $em->getRepository(Payment::class)->findOneBy([
            'order' => $order,
            'method' => 'stripe'
        ]);

        try {
            if ($payment) {
                // Utilisation de round()
                $intent = PaymentIntent::update($payment->getTransactionId(), [
                    'amount' => (int)round($total * 100),
                ]);
            } else {
                $idempotencyKey = 'order_intent_' . $order->getId();

                $intent = PaymentIntent::create([
                    'amount' => (int)round($total * 100),
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => ['order_id' => $order->getId()]
                ], [
                    'idempotency_key' => $idempotencyKey
                ]);

                $payment = new Payment();
                $payment->setOrder($order);
                $payment->setMethod('stripe');
                $payment->setTransactionId($intent->id);
            }

            $payment->setAmount($total);
            $payment->setStatus('pending');

            $em->persist($payment);
            $em->flush();
        } catch (\Exception $e) {
            $em->clear(); 
            $payment = $em->getRepository(Payment::class)->findOneBy([
                'order' => $order,
                'method' => 'stripe'
            ]);

            if (!$payment) {
                return $this->json(['error' => 'Erreur critique lors de la création du paiement'], 500);
            }

            $intent = PaymentIntent::retrieve($payment->getTransactionId());
        }

        return $this->json([
            'clientSecret' => $intent->client_secret,
            'total' => $total
        ]);
    }

    #[Route('/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request, EntityManagerInterface $em, EmailService $emailService, StockService $stockService): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $endpointSecret = $this->getParameter('stripe_webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;

            $payment = $em->getRepository(Payment::class)->findOneBy([
                'transactionId' => $paymentIntent->id
            ]);

            if ($payment) {
                if ($payment->getStatus() === 'success') {
                    return new Response('Already processed', 200);
                }

                $payment->setStatus('success');
                $order = $payment->getOrder();
                $order->setStatus('paid');
                $em->flush();

                $stockService->decrementStock($order);

                if ($order->getShippingMethod() === 'pickup') {
                    $emailService->sendPickupConfirmation($order);
                } else {
                    $emailService->sendOrderConfirmation($order);
                }

                $emailService->sendAdminNotification($order);
            }
        }

        return new Response('Webhook Handled', 200);
    }
}