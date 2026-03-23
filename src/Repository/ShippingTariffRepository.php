<?php

namespace App\Repository;

use App\Entity\ShippingTariff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingTariff>
 *
 * @method ShippingTariff|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShippingTariff|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShippingTariff[]    findAll()
 * @method ShippingTariff[]    findBy(array $criteria, array $orderBy = null)
 */
class ShippingTariffRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingTariff::class);
    }

    /**
     * Finds the most suitable shipping tariff for a specific package.
     * * Logic: Searches for the smallest weight tier that is greater than or equal 
     * to the total package weight, filtered by country and shipping mode.
     *
     * @param string $countryCode ISO country code (e.g., 'FR')
     * @param string $modeCode Shipping mode code (e.g., 'pr', 'locker', 'home')
     * @param int $weightInGrams Total weight of the package in grams
     * @return ShippingTariff|null The matching tariff entity containing the price
     */
    public function findTariff(string $countryCode, string $modeCode, int $weightInGrams): ?ShippingTariff
    {
        return $this->createQueryBuilder('t')
            // Filter by exact country and shipping method
            ->andWhere('t.countryCode = :country')
            ->setParameter('country', $countryCode)
            ->andWhere('t.modeCode = :mode')
            ->setParameter('mode', $modeCode)
            
            // Filter tiers that can accommodate the package weight
            ->andWhere('t.weightMaxG >= :weight')
            ->setParameter('weight', $weightInGrams)

            // Order by maximum weight ascending to find the closest (cheapest) tier
            ->orderBy('t.weightMaxG', 'ASC')
            
            // Limit to 1 to retrieve the single best matching result
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}