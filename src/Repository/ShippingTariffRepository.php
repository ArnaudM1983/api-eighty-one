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
     * Trouve le tarif le plus adapté pour une expédition.
     * * Recherche le palier de poids le plus petit qui est supérieur ou égal
     * au poids total du colis, pour le mode et pays spécifiés.
     *
     * @param string $countryCode Code ISO du pays (ex: 'FR')
     * @param string $modeCode Code du mode de livraison (ex: 'pr' ou 'locker')
     * @param int $weightInGrams Poids total du colis en grammes
     * @return ShippingTariff|null L'entité tarifaire trouvée (contient priceHt)
     */
    public function findTariff(string $countryCode, string $modeCode, int $weightInGrams): ?ShippingTariff
    {
        return $this->createQueryBuilder('t')
            // Filtrer par le pays et le mode de livraison exacts
            ->andWhere('t.countryCode = :country')
            ->setParameter('country', $countryCode)
            ->andWhere('t.modeCode = :mode')
            ->setParameter('mode', $modeCode)
            
            // Filtrer les paliers de poids qui sont supérieurs ou égaux au poids du colis
            ->andWhere('t.weightMaxG >= :weight')
            ->setParameter('weight', $weightInGrams)

            // Trier par poids maximum pour trouver le palier le plus proche (le plus petit)
            ->orderBy('t.weightMaxG', 'ASC')
            
            // Limiter à 1 pour récupérer le premier résultat (le tarif le plus petit et suffisant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}