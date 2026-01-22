<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    #[Route('/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function getStats(OrderRepository $orderRepo): JsonResponse
    {
        try {
            // --- DÉFINITION DES PÉRIODES ---
            $startOfCurrentMonth = new \DateTime('first day of this month 00:00:00');
            
            $startOfLastMonth = new \DateTime('first day of last month 00:00:00');
            $endOfLastMonth = new \DateTime('last day of last month 23:59:59');

            // --- RÉCUPÉRATION DES DONNÉES (MOIS EN COURS) ---
            $currentRevenue = (float)($orderRepo->getRevenueSince($startOfCurrentMonth) ?? 0.0);
            $currentOrderCount = (int)($orderRepo->countSince($startOfCurrentMonth) ?? 0);

            // --- RÉCUPÉRATION DES DONNÉES (MOIS DERNIER) ---
            // Note: Ces méthodes doivent être présentes dans ton OrderRepository
            $lastMonthRevenue = (float)($orderRepo->getRevenueBetween($startOfLastMonth, $endOfLastMonth) ?? 0.0);
            $lastMonthOrderCount = (int)($orderRepo->countBetween($startOfLastMonth, $endOfLastMonth) ?? 0);

            // --- CALCUL DES TENDANCES (%) ---
            $calculateTrend = fn($current, $previous) => 
                $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;

            $revenueTrend = $calculateTrend($currentRevenue, $lastMonthRevenue);
            $orderTrend = $calculateTrend($currentOrderCount, $lastMonthOrderCount);

            // Panier Moyen
            $currentAvgBasket = $currentOrderCount > 0 ? $currentRevenue / $currentOrderCount : 0;
            $lastAvgBasket = $lastMonthOrderCount > 0 ? $lastMonthRevenue / $lastMonthOrderCount : 0;
            $basketTrend = $calculateTrend($currentAvgBasket, $lastAvgBasket);

            // --- ACTIVITÉS RÉCENTES ---
            $recentOrdersRaw = $orderRepo->findBy([], ['createdAt' => 'DESC'], 4);
            $recentOrders = array_map(function($o) {
                $shipping = $o->getShippingInfo();
                return [
                    'id' => $o->getId(),
                    'customerName' => $shipping ? ($shipping->getFirstName() . ' ' . $shipping->getLastName()) : 'Client Inconnu',
                    'total' => number_format((float)$o->getTotal(), 2, ',', ' ') . ' €',
                    'date' => $o->getCreatedAt()->format('d/m H:i'),
                ];
            }, $recentOrdersRaw);

            // --- GRAPHIQUE ---
            $chartData = [
                ['name' => 'Sem 1', 'sales' => round($currentRevenue * 0.2, 2)],
                ['name' => 'Sem 2', 'sales' => round($currentRevenue * 0.5, 2)],
                ['name' => 'Sem 3', 'sales' => round($currentRevenue * 0.8, 2)],
                ['name' => 'Sem 4', 'sales' => round($currentRevenue, 2)],
            ];

            return $this->json([
                'revenue' => [
                    'value' => number_format($currentRevenue, 2, ',', ' ') . ' €',
                    'raw' => $currentRevenue,
                    'month' => (new \DateTime())->format('F'),
                    'trend' => $revenueTrend
                ],
                'orders' => [
                    'count' => $currentOrderCount,
                    'trend' => $orderTrend
                ],
                'basket' => [
                    'value' => number_format($currentAvgBasket, 2, ',', ' ') . ' €',
                    'trend' => $basketTrend
                ],
                'recentOrders' => $recentOrders,
                'chartData' => $chartData
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}