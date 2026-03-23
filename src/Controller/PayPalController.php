<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Payment;
use App\Service\EmailService;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/payment/paypal')]
class PayPalController extends AbstractController
{
    public function __construct(private HttpClientInterface $client) {}

    /**
     * Determine the base URL depending on the environment (Sandbox vs Live)
     */
    private function getBaseUrl(): string
    {
        return $this->getParameter('paypal_mode') === 'LIVE'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Fetch OAuth2 Access Token from PayPal API
     */
    private function getAccessToken(): ?string
    {
        $url = $this->getBaseUrl() . '/v1/oauth2/token';
        try {
            $response = $this->client->request('POST', $url, [
                'auth_basic' => [$this->getParameter('paypal_client_id'), $this->getParameter('paypal_client_secret')],
                'body' => 'grant_type=client_credentials'
            ]);
            return $response->toArray()['access_token'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * CREATE ORDER
     * Initialize the transaction on PayPal's side and store a pending payment in DB.
     */
    #[Route('/create/{id}', name: 'api_paypal_create', methods: ['POST'])]
    public function createOrder(Order $order, EntityManagerInterface $em): JsonResponse
    {
        $existingPayment = $em->getRepository(Payment::class)->findOneBy(['order' => $order]);

        if ($existingPayment) {
            if ($existingPayment->getStatus() === 'success') {
                 return $this->json(['error' => 'Commande déjà payée'], 400);
            }
            
            // Cleanup previous failed or pending attempts
            if ($existingPayment->getStatus() === 'pending') {
                $em->remove($existingPayment);
                $em->flush();
            }
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return $this->json(['error' => 'Erreur connexion PayPal'], 500);

        $url = $this->getBaseUrl() . '/v2/checkout/orders';
        
        // Recalculate total and format it for PayPal (must be a string with 2 decimals)
        $totalFloat = $order->calculateTotal();
        $order->setTotal($totalFloat);
        $total = number_format($totalFloat, 2, '.', '');

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    "intent" => "CAPTURE",
                    "purchase_units" => [[
                        "reference_id" => (string)$order->getId(),
                        "amount" => [
                            "currency_code" => "EUR",
                            "value" => $total
                        ],
                        "description" => "Commande #" . $order->getId()
                    ]]
                ]
            ]);

            $paypalOrderId = $response->toArray()['id'];

            // Save pending payment record
            $payment = new Payment();
            $payment->setOrder($order);
            $payment->setMethod('paypal');
            $payment->setTransactionId($paypalOrderId);
            $payment->setAmount($totalFloat);
            $payment->setStatus('pending');

            $em->persist($payment);
            $em->flush();

            return $this->json(['id' => $paypalOrderId]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * CAPTURE ORDER
     * Verify the payment success with PayPal, update stock, and send notifications.
     */
    #[Route('/capture/{id}', name: 'api_paypal_capture', methods: ['POST'])]
    public function captureOrder(Request $request, Order $order, EntityManagerInterface $em, EmailService $emailService, StockService $stockService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $paypalOrderId = $data['paypalOrderId'] ?? null;

        if (!$paypalOrderId) return $this->json(['error' => 'ID manquant'], 400);

        $accessToken = $this->getAccessToken();
        $url = $this->getBaseUrl() . "/v2/checkout/orders/{$paypalOrderId}/capture";

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json'
                ]
            ]);

            $result = $response->toArray();

            if (isset($result['status']) && $result['status'] === 'COMPLETED') {
                
                $payment = $em->getRepository(Payment::class)->findOneBy(['transactionId' => $paypalOrderId]);
                
                if ($payment) {
                    if ($payment->getStatus() === 'success') {
                        return $this->json(['status' => 'COMPLETED']);
                    }

                    // Transactional updates
                    $payment->setStatus('success');
                    $order->setStatus('paid');
                    $em->flush();

                    // Inventory management
                    $stockService->decrementStock($order);
                    
                    // Automated notifications
                    if ($order->getShippingMethod() === 'pickup') {
                        $emailService->sendPickupConfirmation($order);
                    } else {
                        $emailService->sendOrderConfirmation($order);
                    }
                    $emailService->sendAdminNotification($order);

                    return $this->json(['status' => 'COMPLETED']);
                }
            }

            return $this->json(['error' => 'Échec paiement'], 400);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}