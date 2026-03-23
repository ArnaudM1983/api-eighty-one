<?php

namespace App\Repository;

use App\Entity\InstagramToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InstagramTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstagramToken::class);
    }

    public function findLatest(): ?InstagramToken
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.expiresAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getValidToken(): ?string
    {
        $token = $this->findLatest();
        if ($token && $token->getExpiresAt() > new \DateTime()) {
            return $token->getToken();
        }
        return null;
    }
}
