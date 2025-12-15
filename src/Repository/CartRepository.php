<?php

namespace App\Repository;

use App\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function save(Cart $cart, bool $flush = true): void
    {
        $this->getEntityManager()->persist($cart);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Cart $cart, bool $flush = true): void
    {
        $this->getEntityManager()->remove($cart);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function findOneByToken(string $token): ?Cart
    {
        return $this->findOneBy(['token' => $token]);
    }
}
