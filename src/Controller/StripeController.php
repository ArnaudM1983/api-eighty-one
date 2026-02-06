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
    public function createIntent(Order $order, EntityManagerInterface $em): JsonResponse
    {
        Stripe::setApiKey($this->getParameter('stripe_secret_key'));
        $total = $order->getTotal();

        // --- NETTOYAGE PRÉVENTIF ---
        // On vérifie s'il y a un brouillon de paiement qui n'est PAS du Stripe (ex: PayPal annulé)
        // ou un vieux Stripe 'pending' qu'on voudrait écraser.
        $existingPayment = $em->getRepository(Payment::class)->findOneBy(['order' => $order]);

        if ($existingPayment && $existingPayment->getStatus() === 'pending') {
            // Si on change de méthode (ex: c'était PayPal) ou si on veut forcer un reset
            if ($existingPayment->getMethod() !== 'stripe') {
                $em->remove($existingPayment);
                $em->flush();
                // On met $payment à null pour forcer la création d'un nouveau
                $existingPayment = null; 
            }
        }
        // ---------------------------

        // 1. On cherche s'il y a déjà un paiement Stripe valide en cours
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'order' => $order,
            'method' => 'stripe'
        ]);

        try {
            if ($payment) {
                // Si trouvé, on met à jour l'existant chez Stripe
                $intent = PaymentIntent::update($payment->getTransactionId(), [
                    'amount' => (int)($total * 100),
                ]);
            } else {
                // --- FIX STRIPE : Clé d'idempotence ---
                // Si on appelle cette ligne 2 fois, Stripe ne créera qu'un seul paiement
                $idempotencyKey = 'order_intent_' . $order->getId();

                $intent = PaymentIntent::create([
                    'amount' => (int)($total * 100),
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
            // Gestion de concurrence : si BDD en erreur (UniqueConstraint), on recharge
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
                // Protection pour ne pas traiter 2 fois
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