<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    // Exemple : récupérer toutes les commandes d'un utilisateur
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Exemple : récupérer toutes les commandes avec un certain statut
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Exemple : récupérer les commandes récentes (limit)
    public function findRecentOrders(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le CA des commandes payées depuis le 1er du mois
     */
    public function getRevenueSince(\DateTimeInterface $date): float
    {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->where('o.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        // Si le résultat est null (pas de commandes), on retourne 0.0
        return (float) ($result ?? 0.0);
    }

    /**
     * Compte le nombre de commandes depuis le 1er du mois
     */
    public function countSince(\DateTimeInterface $date): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule le CA entre deux dates précises
     */
    public function getRevenueBetween(\DateTime $start, \DateTime $end): ?float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de commandes entre deux dates précises
     */
    public function countBetween(\DateTime $start, \DateTime $end): ?int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Ajout d'autres méthodes personnalisées ici
}
