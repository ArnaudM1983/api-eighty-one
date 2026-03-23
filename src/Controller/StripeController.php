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
    /**
     * Step 1: Create or Update a Stripe PaymentIntent.
     * Ensures the order total (including shipping) is synchronized with Stripe.
     */
    #[Route('/stripe/create-intent/{id}', name: 'api_stripe_intent', methods: ['POST'])]
    public function createIntent(Request $request, Order $order, EntityManagerInterface $em): JsonResponse
    {
        // 1. Retrieve shipping data sent by the Front-end
        $data = json_decode($request->getContent(), true) ?? [];

        // 2. Update the order with the latest shipping costs before calculating the Stripe total
        if (isset($data['shippingCost'])) {
            $order->setShippingCost((float)$data['shippingCost']);
            if (isset($data['shippingMethod'])) {
                $order->setShippingMethod($data['shippingMethod']);
            }
            $em->flush(); 
        }

        Stripe::setApiKey($this->getParameter('stripe_secret_key'));
        
        // 3. Guaranteed calculation with shipping fees
        $total = $order->calculateTotal();
        $order->setTotal($total);

        // Check for existing pending payments to avoid duplicates
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
                // Update existing Intent if amount changed
                $intent = PaymentIntent::update($payment->getTransactionId(), [
                    'amount' => (int)round($total * 100),
                ]);
            } else {
                // Use an Idempotency Key to prevent duplicate charges on Stripe's side
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
            // Safety fallback: Retrieve the intent if creation failed due to a timeout or duplicate key
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

    /**
     * Step 2: Handle Stripe Webhooks.
     * This is the only reliable way to confirm payment success (Asynchronous).
     */
    #[Route('/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request, EntityManagerInterface $em, EmailService $emailService, StockService $stockService): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $endpointSecret = $this->getParameter('stripe_webhook_secret');

        try {
            // Verify the webhook signature to ensure it's coming from Stripe
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        // Handle the successful payment event
        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;

            $payment = $em->getRepository(Payment::class)->findOneBy([
                'transactionId' => $paymentIntent->id
            ]);

            if ($payment) {
                // Prevent duplicate processing
                if ($payment->getStatus() === 'success') {
                    return new Response('Already processed', 200);
                }

                // 1. Update Payment and Order status
                $payment->setStatus('success');
                $order = $payment->getOrder();
                $order->setStatus('paid');
                $em->flush();

                // 2. Inventory Management: Reserve products
                $stockService->decrementStock($order);

                // 3. Customer mail
                if ($order->getShippingMethod() === 'pickup') {
                    $emailService->sendPickupConfirmation($order);
                } else {
                    $emailService->sendOrderConfirmation($order);
                }

                // 4. Admin Notification
                $emailService->sendAdminNotification($order);
            }
        }

        return new Response('Webhook Handled', 200);
    }
}