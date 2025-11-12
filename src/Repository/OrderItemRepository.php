<?php

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\Order;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 *
 * @method OrderItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderItem[]    findAll()
 * @method OrderItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    // Exemple : récupérer tous les items d'une commande
    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('oi')
            ->andWhere('oi.order = :order')
            ->setParameter('order', $order)
            ->orderBy('oi.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Exemple : récupérer tous les items pour un produit donné
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('oi')
            ->andWhere('oi.product = :product')
            ->setParameter('product', $product)
            ->orderBy('oi.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Tu peux ajouter d'autres méthodes personnalisées ici
}
