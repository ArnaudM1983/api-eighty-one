<?php
namespace App\Repository;

use App\Entity\ShippingInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingInfo>
 *
 * @method ShippingInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShippingInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShippingInfo[]    findAll()
 * @method ShippingInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShippingInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingInfo::class);
    }

    public function save(ShippingInfo $shippingInfo, bool $flush = true): void
    {
        $this->_em->persist($shippingInfo);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function remove(ShippingInfo $shippingInfo, bool $flush = true): void
    {
        $this->_em->remove($shippingInfo);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Retourne la ShippingInfo associée à une commande spécifique
     */
    public function findByOrderId(int $orderId): ?ShippingInfo
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.order = :orderId')
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Exemple : rechercher toutes les commandes expédiées dans un pays donné
     */
    public function findByCountry(string $country): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.country = :country')
            ->setParameter('country', $country)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
