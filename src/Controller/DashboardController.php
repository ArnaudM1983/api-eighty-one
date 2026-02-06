<?php

namespace App\Controller;

use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    #[Route('/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function getStats(
        OrderRepository $orderRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            $now = new \DateTime();
            $startOfCurrentMonth = new \DateTime('first day of this month 00:00:00');
            $startOfLastMonth = new \DateTime('first day of last month 00:00:00');
            $endOfLastMonth = new \DateTime('last day of last month 23:59:59');

            // --- 1. CHIFFRES CLÉS ---
            $currentRevenue = (float)($orderRepo->getRevenueSince($startOfCurrentMonth) ?? 0.0);
            $currentOrderCount = (int)($orderRepo->countSince($startOfCurrentMonth) ?? 0);
            $lastMonthRevenue = (float)($orderRepo->getRevenueBetween($startOfLastMonth, $endOfLastMonth) ?? 0.0);

            // Revenu RÉEL Encaissé (Somme des paiements success)
            $actualCollected = (float)$em->createQueryBuilder()
                ->select('SUM(p.amount)')
                ->from(Payment::class, 'p')
                ->where('p.status = :status')
                ->andWhere('p.createdAt >= :startDate') 
                ->setParameter('status', 'success')
                ->setParameter('startDate', $startOfCurrentMonth) 
                ->getQuery()->getSingleScalarResult() ?? 0.0;

            $calculateTrend = fn($current, $previous) =>
            $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;

            $currentAvgBasket = $currentOrderCount > 0 ? $currentRevenue / $currentOrderCount : 0;

            // --- 2. MEILLEURES VENTES (Par Variantes) ---
            $bestSellersRaw = $em->createQueryBuilder()
                ->select(
                    'p.name as productName',
                    'v.name as variantName',
                    'SUM(oi.quantity) as totalSales',
                    'SUM(oi.quantity * oi.price) as totalRevenue'
                )
                ->from(OrderItem::class, 'oi')
                ->join('oi.product', 'p')
                ->leftJoin('oi.variant', 'v')
                ->join('oi.order', 'o')
                ->where('o.status IN (:statuses)')
                ->setParameter('statuses', ['paid', 'shipped', 'completed'])
                ->groupBy('p.id', 'v.id')
                ->orderBy('totalSales', 'DESC')
                ->setMaxResults(5)
                ->getQuery()->getResult();

            // Formatage propre pour le Dashboard React
            $bestSellers = array_map(fn($b) => [
                'name' => $b['productName'] . ($b['variantName'] ? ' (' . $b['variantName'] . ')' : ''),
                'sales' => (int)$b['totalSales'],
                'revenue' => number_format((float)$b['totalRevenue'], 2, '.', '')
            ], $bestSellersRaw);

            // --- 3. ACTIVITÉS RÉCENTES ---
            $recentOrdersRaw = $orderRepo->findBy([], ['createdAt' => 'DESC'], 5);
            $recentOrders = array_map(function ($o) {
                $shipping = $o->getShippingInfo();
                return [
                    'id' => $o->getId(),
                    'customerName' => $shipping ? ($shipping->getFirstName() . ' ' . $shipping->getLastName()) : 'Client Inconnu',
                    'total' => number_format((float)$o->getTotal(), 2, '.', ''),
                    'date' => $o->getCreatedAt()->format('d/m H:i'),
                    'status' => $o->getStatus()
                ];
            }, $recentOrdersRaw);

            // --- 4. COMMANDES A PREPARER ---
            $toPrepareCount = (int)$orderRepo->count(['status' => 'paid']);

            return $this->json([
                'revenue' => [
                    'value' => number_format($currentRevenue, 2, ',', ' ') . ' €',
                    'actualCollected' => number_format($actualCollected, 2, ',', ' ') . ' €',
                    'month' => $now->format('F'),
                    'trend' => $calculateTrend($currentRevenue, $lastMonthRevenue)
                ],
                'orders' => [
                    'count' => $currentOrderCount,
                    'trend' => 0 // Tu peux calculer la tendance ici aussi si nécessaire
                ],
                'basket' => [
                    'value' => number_format($currentAvgBasket, 2, ',', ' ') . ' €',
                ],
                'bestSellers' => $bestSellers, // Utilise la variable déjà mappée
                'recentOrders' => $recentOrders,
                'chartData' => [
                    ['name' => 'Sem 1', 'sales' => round($currentRevenue * 0.25, 2)],
                    ['name' => 'Sem 2', 'sales' => round($currentRevenue * 0.45, 2)],
                    ['name' => 'Sem 3', 'sales' => round($currentRevenue * 0.75, 2)],
                    ['name' => 'Sem 4', 'sales' => round($currentRevenue, 2)],
                ],
                'logistics' => [
                    'toPrepare' => $toPrepareCount
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }
}
