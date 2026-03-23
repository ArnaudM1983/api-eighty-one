<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payments')]
#[IsGranted('ROLE_ADMIN')] // Security: Access restricted to administrators for financial auditing
class PaymentController extends AbstractController
{
    /**
     * Lists all payment transactions (Financial Audit Log).
     * Provides a reverse-chronological view of all payment attempts and successes.
     */
    #[Route('', name: 'api_payment_list', methods: ['GET'])]
    public function list(PaymentRepository $repo, Request $request): JsonResponse
    {
        // Fetch all transactions, ordered from most recent to oldest
        $payments = $repo->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(function ($p) {
            return [
                'id' => $p->getId(),
                'status' => $p->getStatus(),
                'method' => $p->getMethod(),
                'amount' => $p->getAmount(),
                'transactionId' => $p->getTransactionId(),
                'createdAt' => $p->getCreatedAt()->format('d/m/Y H:i'),
                // Include linked order details for dashboard navigation
                'order' => [
                    'id' => $p->getOrder()?->getId(),
                    'total' => $p->getOrder()?->getTotal(),
                ],
            ];
        }, $payments);

        return $this->json($data);
    }

    /**
     * Retrieve detailed information for a specific payment transaction.
     */
    #[Route('/{id}', name: 'api_payment_get', methods: ['GET'])]
    public function getOne(int $id, PaymentRepository $repo): JsonResponse
    {
        $p = $repo->find($id);
        if (!$p) return $this->json(['error' => 'Paiement introuvable'], 404);

        return $this->json([
            'id' => $p->getId(),
            'status' => $p->getStatus(),
            'method' => $p->getMethod(),
            'amount' => $p->getAmount(),
            'transactionId' => $p->getTransactionId(),
            'createdAt' => $p->getCreatedAt()->format('d/m/Y H:i'),
            'updatedAt' => $p->getUpdatedAt()?->format('d/m/Y H:i'),
            'orderId' => $p->getOrder()?->getId()
        ]);
    }
}