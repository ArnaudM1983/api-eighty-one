<?php
namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 *
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $payment, bool $flush = true): void
    {
        $this->_em->persist($payment);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function remove(Payment $payment, bool $flush = true): void
    {
        $this->_em->remove($payment);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Retourne tous les paiements réussis pour une commande donnée
     */
    public function findSuccessfulPaymentsByOrderId(int $orderId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.order = :orderId')
            ->andWhere('p.status = :status')
            ->setParameter('orderId', $orderId)
            ->setParameter('status', 'success')
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le montant total payé pour une commande
     */
    public function getTotalPaidByOrderId(int $orderId): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.order = :orderId')
            ->andWhere('p.status = :status')
            ->setParameter('orderId', $orderId)
            ->setParameter('status', 'success')
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result;
    }

    /**
     * Retourne les paiements en attente pour une commande
     */
    public function findPendingByOrderId(int $orderId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.order = :orderId')
            ->andWhere('p.status = :status')
            ->setParameter('orderId', $orderId)
            ->setParameter('status', 'pending')
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
