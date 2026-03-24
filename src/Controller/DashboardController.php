<?php

namespace App\Controller;

use App\Entity\OrderItem;
use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function getStats(
        Request $request,
        OrderRepository $orderRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            $period = $request->query->get('period', 'month');
            $now = new \DateTime();
            $today = new \DateTime('today 00:00:00');

            // --- 1. PERIOD LOGIC (Current vs Previous) ---
            switch ($period) {
                case 'today':
                    $startDate = clone $today;
                    $endDate = new \DateTime('today 23:59:59');
                    // Compare to: Yesterday same time (or full day)
                    $prevStart = (clone $startDate)->modify('-1 day');
                    $prevEnd = (clone $endDate)->modify('-1 day');
                    
                    $periodLabel = "Aujourd'hui";
                    $intervalSpec = 'PT1H'; 
                    $chartKeyFormat = 'Y-m-d H';
                    $chartDisplayFormat = 'H\h';
                    break;

                case '7days':
                    $startDate = (new \DateTime())->modify('-6 days')->setTime(0, 0, 0);
                    $endDate = new \DateTime('now');
                    // Compare to: The 7 days before
                    $prevStart = (clone $startDate)->modify('-7 days');
                    $prevEnd = (clone $startDate)->modify('-1 second');

                    $periodLabel = "7 derniers jours";
                    $intervalSpec = 'P1D'; 
                    $chartKeyFormat = 'Y-m-d';
                    $chartDisplayFormat = 'd/m';
                    break;

                case 'last_month':
                    $startDate = new \DateTime('first day of last month 00:00:00');
                    $endDate = new \DateTime('last day of last month 23:59:59');
                    // Compare to: Month before last month
                    $prevStart = (clone $startDate)->modify('-1 month');
                    $prevEnd = (clone $startDate)->modify('-1 second');

                    $periodLabel = "Mois dernier";
                    $intervalSpec = 'P1D';
                    $chartKeyFormat = 'Y-m-d';
                    $chartDisplayFormat = 'd/m';
                    break;

                case 'year':
                    $startDate = new \DateTime('first day of January this year 00:00:00');
                    $endDate = new \DateTime('now');
                    // Compare to: Last year same period (Year-to-date)
                    $prevStart = (clone $startDate)->modify('-1 year');
                    $prevEnd = (clone $endDate)->modify('-1 year');

                    $periodLabel = "Cette année";
                    $intervalSpec = 'P1M';
                    $chartKeyFormat = 'Y-m';
                    $chartDisplayFormat = 'M';
                    break;

                case 'month':
                default:
                    $startDate = new \DateTime('first day of this month 00:00:00');
                    $endDate = new \DateTime('now');
                    // Compare to: Last month same period (Month-to-date)
                    $prevStart = (clone $startDate)->modify('-1 month');
                    $prevEnd = (clone $endDate)->modify('-1 month');

                    $periodLabel = "Ce mois-ci";
                    $intervalSpec = 'P1D';
                    $chartKeyFormat = 'Y-m-d';
                    $chartDisplayFormat = 'd/m';
                    break;
            }

            // --- 2. FETCH DATA ---
            $statuses = ['paid', 'shipped', 'completed'];

            // Query helper for Global Stats
            $getStatsForRange = function($start, $end) use ($orderRepo, $statuses) {
                return $orderRepo->createQueryBuilder('o')
                    ->select('SUM(o.total) as totalRev, COUNT(o.id) as totalCount, SUM(o.shippingCost) as totalShip')
                    ->where('o.createdAt BETWEEN :start AND :end')
                    ->andWhere('o.status IN (:statuses)')
                    ->setParameter('start', $start)
                    ->setParameter('end', $end)
                    ->setParameter('statuses', $statuses)
                    ->getQuery()->getSingleResult();
            };

            $currentData = $getStatsForRange($startDate, $endDate);
            $prevData = $getStatsForRange($prevStart, $prevEnd);

            $currentGlobalRev = (float)($currentData['totalRev'] ?? 0.0);
            $currentOrderCount = (int)($currentData['totalCount'] ?? 0);
            $currentShipping = (float)($currentData['totalShip'] ?? 0.0);

            $prevGlobalRev = (float)($prevData['totalRev'] ?? 0.0);
            $prevOrderCount = (int)($prevData['totalCount'] ?? 0);

            // --- 3. TREND CALCULATION ---
            $calcTrend = function($curr, $prev) {
                if ($prev <= 0) return ($curr > 0) ? 100 : 0;
                return round((($curr - $prev) / $prev) * 100, 1);
            };

            $revenueTrend = $calcTrend($currentGlobalRev, $prevGlobalRev);
            $orderTrend = $calcTrend($currentOrderCount, $prevOrderCount);

            // --- 4. NET TOTALS & BASKET ---
            $productRevenueTTC = $currentGlobalRev - $currentShipping;
            $productRevenueHT = $productRevenueTTC / 1.2;
            $avgBasket = $currentOrderCount > 0 ? $productRevenueTTC / $currentOrderCount : 0;

            // --- 5. BEST SELLERS ---
            $bestSellersRaw = $em->createQueryBuilder()
                ->select('p.name as pName, v.name as vName, SUM(oi.quantity) as qty, SUM(oi.quantity * oi.price) as rev')
                ->from(OrderItem::class, 'oi')
                ->join('oi.product', 'p')
                ->leftJoin('oi.variant', 'v')
                ->join('oi.order', 'o')
                ->where('o.createdAt BETWEEN :start AND :end')
                ->andWhere('o.status IN (:statuses)')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->setParameter('statuses', $statuses)
                ->groupBy('p.id', 'v.id')
                ->orderBy('qty', 'DESC')
                ->setMaxResults(5)
                ->getQuery()->getResult();

            $bestSellers = array_map(fn($b) => [
                'name' => $b['pName'] . ($b['vName'] ? ' (' . $b['vName'] . ')' : ''),
                'sales' => (int)$b['qty'],
                'revenue' => number_format((float)$b['rev'], 2, '.', '')
            ], $bestSellersRaw);

            // --- 6. CHART DATA ---
            $chartDataMap = [];
            $interval = new \DateInterval($intervalSpec);
            // Add a small buffer to end date to ensure the last point is included
            $chartEnd = (clone $endDate)->modify($period === 'today' ? '+0 second' : '+1 day');
            $datePeriod = new \DatePeriod($startDate, $interval, $chartEnd);

            foreach ($datePeriod as $dt) {
                $key = $dt->format($chartKeyFormat);
                $display = $dt->format($chartDisplayFormat);
                
                if ($period === 'year') {
                    $monthsFr = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
                    $display = $monthsFr[(int)$dt->format('n') - 1];
                }
                $chartDataMap[$key] = ['name' => $display, 'sales' => 0];
            }

            $ordersForChart = $orderRepo->createQueryBuilder('o')
                ->where('o.createdAt BETWEEN :start AND :end')
                ->andWhere('o.status IN (:statuses)')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->setParameter('statuses', $statuses)
                ->getQuery()->getResult();

            foreach ($ordersForChart as $o) {
                $key = $o->getCreatedAt()->format($chartKeyFormat);
                if (isset($chartDataMap[$key])) {
                    $chartDataMap[$key]['sales'] += ($o->getTotal() - $o->getShippingCost());
                }
            }

            // --- 7. FINAL RESPONSE ---
            return $this->json([
                'revenue' => [
                    'productTTC' => number_format($productRevenueTTC, 2, ',', ' ') . ' €',
                    'productHT' => number_format($productRevenueHT, 2, ',', ' ') . ' €',
                    'month' => $periodLabel,
                    'trend' => $revenueTrend
                ],
                'orders' => [
                    'count' => $currentOrderCount,
                    'trend' => $orderTrend
                ],
                'basket' => [
                    'value' => number_format($avgBasket, 2, ',', ' ') . ' €',
                ],
                'bestSellers' => $bestSellers,
                'chartData' => array_values($chartDataMap),
                'logistics' => [
                    'toPrepare' => (int)$orderRepo->count(['status' => 'paid'])
                ],
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}