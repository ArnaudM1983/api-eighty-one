<?php

namespace App\Repository;

use App\Entity\CartItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function save(CartItem $item, bool $flush = true): void
    {
        $this->getEntityManager()->persist($item);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(CartItem $item, bool $flush = true): void
    {
        $this->getEntityManager()->remove($item);
        if ($flush) $this->getEntityManager()->flush();
    }
}
