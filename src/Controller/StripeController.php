<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/payment')]
class StripeController extends AbstractController
{
    #[Route('/stripe/create-intent/{id}', name: 'api_stripe_intent', methods: ['POST'])]
    public function createIntent(Order $order, EntityManagerInterface $em): JsonResponse
    {
        Stripe::setApiKey($this->getParameter('stripe_secret_key'));

        $total = $order->getTotal();
        if (!$total || $total <= 0) {
            return $this->json(['error' => 'Montant de commande invalide'], 400);
        }

        // 1. Chercher si un paiement Stripe "pending" existe déjà pour cette commande
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'order' => $order,
            'status' => 'pending',
            'method' => 'stripe'
        ]);

        try {
            if ($payment) {
                // Optionnel : Mettre à jour le montant chez Stripe si le panier a changé
                $intent = PaymentIntent::update($payment->getTransactionId(), [
                    'amount' => (int)($total * 100),
                ]);
            } else {
                // 2. Sinon, créer un tout nouveau PaymentIntent
                $intent = PaymentIntent::create([
                    'amount' => (int)($total * 100),
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => ['order_id' => $order->getId()]
                ]);

                // Créer l'entrée en base uniquement si elle n'existe pas
                $payment = new Payment();
                $payment->setOrder($order);
                $payment->setMethod('stripe');
                $payment->setTransactionId($intent->id);
            }

            // 3. Toujours synchroniser le montant et le statut
            $payment->setAmount($total);
            $payment->setStatus('pending');

            $em->persist($payment);
            $em->flush();

            return $this->json([
                'clientSecret' => $intent->client_secret,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request, EntityManagerInterface $em): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $endpointSecret = $this->getParameter('stripe_webhook_secret');

        try {
            // Vérification de la signature pour être sûr que ça vient bien de Stripe
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        // Gestion de l'événement de succès
        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object; // L'objet PaymentIntent de Stripe

            // Retrouver le paiement dans votre base de données via l'ID Stripe (pi_...)
            $payment = $em->getRepository(Payment::class)->findOneBy([
                'transactionId' => $paymentIntent->id
            ]);

            if ($payment) {
                // 1. Mettre à jour le paiement
                $payment->setStatus('success');

                // 2. Mettre à jour la commande liée
                $order = $payment->getOrder();
                $order->setStatus('paid');

                // On peut aussi imaginer l'envoi d'un mail ici via un Service

                $em->flush();
            }
        }

        return new Response('Webhook Handled', 200);
    }
}
