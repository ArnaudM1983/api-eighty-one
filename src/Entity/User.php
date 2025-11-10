<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $userLogin;

    #[ORM\Column(type: "string", length: 255)]
    private string $userEmail;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $userRegistered;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: UserMeta::class)]
    private Collection $metas;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->metas = new ArrayCollection();
        $this->orders = new ArrayCollection();
    }

    // getters et setters...
}
