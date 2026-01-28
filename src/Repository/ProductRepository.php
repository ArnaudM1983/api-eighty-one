<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    // Exemple : trouver les produits par prix inférieur à une valeur
    public function findByPriceUnder(float $price): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.price < :price')
            ->setParameter('price', $price)
            ->orderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Exemple : récupérer tous les produits d'une catégorie donnée
    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.categories', 'c')
            ->andWhere('c = :category')
            ->setParameter('category', $category)
            // 1. On trie par la position définie dans le Back-Office (0, 1, 2...)
            ->orderBy('p.position', 'ASC')
            // 2. Si les positions sont identiques (ex: tout le monde à 0), on prend les plus récents
            ->addOrderBy('p.id', 'DESC') 
            ->getQuery()
            ->getResult();
    }

    // Exemple : rechercher un produit par slug
    public function findOneBySlug(string $slug): ?Product
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
