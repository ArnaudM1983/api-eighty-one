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

#[Route('/api/payment')]
class StripeController extends AbstractController
{
    #[Route('/stripe/create-intent/{id}', name: 'api_stripe_intent', methods: ['POST'])]
    public function createIntent(Order $order, EntityManagerInterface $em): JsonResponse
    {
        Stripe::setApiKey($this->getParameter('stripe_secret_key'));
        $total = $order->getTotal();

        // 1. On cherche s'il y a déjà un paiement
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
                // Si non trouvé, on en crée un nouveau
                $intent = PaymentIntent::create([
                    'amount' => (int)($total * 100),
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => ['order_id' => $order->getId()]
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
            // --- LE FIX EST ICI ---
            // Si l'erreur est une violation d'unicité (Requête B), on essaie de récupérer 
            // le paiement que la Requête A vient juste de créer.
            $em->clear(); // On vide l'EntityManager pour rafraîchir les données
            $payment = $em->getRepository(Payment::class)->findOneBy([
                'order' => $order,
                'method' => 'stripe'
            ]);

            if (!$payment) {
                return $this->json(['error' => 'Erreur critique lors de la création du paiement'], 500);
            }

            // On récupère l'intent Stripe pour renvoyer le clientSecret à React
            $intent = PaymentIntent::retrieve($payment->getTransactionId());
        }

        return $this->json([
            'clientSecret' => $intent->client_secret,
            'total' => $total
        ]);
    }

    #[Route('/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request, EntityManagerInterface $em, EmailService $emailService): Response
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
                // Mise à jour du paiement
                $payment->setStatus('success');

                // Mise à jour de la commande liée
                $order = $payment->getOrder();
                $order->setStatus('paid');

                $em->flush();

                // --- ENVOI DE L'EMAIL ---
                $emailService->sendOrderConfirmation($order);
            }
        }

        return new Response('Webhook Handled', 200);
    }
}
