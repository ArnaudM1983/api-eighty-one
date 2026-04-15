<?php

namespace App\Repository;

use App\Entity\Guide;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Guide>
 *
 * @method Guide|null find($id, $lockMode = null, $lockVersion = null)
 * @method Guide|null findOneBy(array $criteria, array $orderBy = null)
 * @method Guide[]    findAll()
 * @method Guide[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GuideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Guide::class);
    }

    public function findOneBySlug(string $slug): ?Guide
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatest(int $limit = 4): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Guide $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Guide $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}