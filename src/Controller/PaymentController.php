<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/payments')]
class PaymentController extends AbstractController
{
    /**
     * Liste tous les paiements (Journal financier)
     */
    #[Route('', name: 'api_payment_list', methods: ['GET'])]
    public function list(PaymentRepository $repo, Request $request): JsonResponse
    {
        // On récupère tout, du plus récent au plus ancien
        $payments = $repo->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(function ($p) {
            return [
                'id' => $p->getId(),
                'status' => $p->getStatus(),
                'method' => $p->getMethod(),
                'amount' => $p->getAmount(),
                'transactionId' => $p->getTransactionId(),
                'createdAt' => $p->getCreatedAt()->format('d/m/Y H:i'),
                // On inclut les infos de la commande pour le lien dans le Dashboard
                'order' => [
                    'id' => $p->getOrder()?->getId(),
                    'total' => $p->getOrder()?->getTotal(),
                ],
            ];
        }, $payments);

        return $this->json($data);
    }

    /**
     * Détail d'un paiement spécifique
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