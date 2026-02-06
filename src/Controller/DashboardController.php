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

            // --- 1. CHIFFRES GLOBAUX (Inclus Livraison) ---
            $currentGlobalRevenue = (float)($orderRepo->getRevenueSince($startOfCurrentMonth) ?? 0.0);
            $currentOrderCount = (int)($orderRepo->countSince($startOfCurrentMonth) ?? 0);
            
            // --- 2. CALCUL FRAIS DE PORT (Mois en cours) ---
            // On récupère la somme des frais de port pour les commandes validées ce mois-ci
            $currentShippingRevenue = (float)$orderRepo->createQueryBuilder('o')
                ->select('SUM(o.shippingCost)')
                ->where('o.createdAt >= :startDate')
                ->andWhere('o.status IN (:statuses)')
                ->setParameter('startDate', $startOfCurrentMonth)
                ->setParameter('statuses', ['paid', 'shipped', 'completed'])
                ->getQuery()
                ->getSingleScalarResult() ?? 0.0;

            // --- 3. CALCUL CA PRODUITS (HT et TTC) ---
            // CA TTC Hors Livraison
            $productRevenueTTC = $currentGlobalRevenue - $currentShippingRevenue;
            
            // CA HT Hors Livraison (Hypothèse TVA 20% -> division par 1.2)
            $productRevenueHT = $productRevenueTTC / 1.2;

            // --- 4. TENDANCE (Basée sur le global pour simplifier) ---
            $lastMonthRevenue = (float)($orderRepo->getRevenueBetween($startOfLastMonth, $endOfLastMonth) ?? 0.0);
            $calculateTrend = fn($current, $previous) =>
            $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
            
            $trend = $calculateTrend($currentGlobalRevenue, $lastMonthRevenue);

            // Panier moyen (basé sur le TTC global, c'est souvent ce qu'on veut voir)
            $currentAvgBasket = $currentOrderCount > 0 ? $currentGlobalRevenue / $currentOrderCount : 0;

            // --- 5. MEILLEURES VENTES ---
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

            $bestSellers = array_map(fn($b) => [
                'name' => $b['productName'] . ($b['variantName'] ? ' (' . $b['variantName'] . ')' : ''),
                'sales' => (int)$b['totalSales'],
                'revenue' => number_format((float)$b['totalRevenue'], 2, '.', '')
            ], $bestSellersRaw);

            // --- 6. ACTIVITÉS RÉCENTES ---
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

            $toPrepareCount = (int)$orderRepo->count(['status' => 'paid']);

            return $this->json([
                'revenue' => [
                    // On envoie les nouvelles valeurs calculées
                    'productTTC' => number_format($productRevenueTTC, 2, ',', ' ') . ' €',
                    'productHT' => number_format($productRevenueHT, 2, ',', ' ') . ' €',
                    'month' => $now->format('F'),
                    'trend' => $trend
                ],
                'orders' => [
                    'count' => $currentOrderCount,
                    'trend' => 0
                ],
                'basket' => [
                    'value' => number_format($currentAvgBasket, 2, ',', ' ') . ' €',
                ],
                'bestSellers' => $bestSellers,
                'recentOrders' => $recentOrders,
                // Le chart reste sur le global pour l'instant
                'chartData' => [
                    ['name' => 'Sem 1', 'sales' => round($currentGlobalRevenue * 0.25, 2)],
                    ['name' => 'Sem 2', 'sales' => round($currentGlobalRevenue * 0.45, 2)],
                    ['name' => 'Sem 3', 'sales' => round($currentGlobalRevenue * 0.75, 2)],
                    ['name' => 'Sem 4', 'sales' => round($currentGlobalRevenue, 2)],
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