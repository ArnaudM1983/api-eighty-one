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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserLogin(): ?string
    {
        return $this->userLogin;
    }

    public function setUserLogin(string $userLogin): static
    {
        $this->userLogin = $userLogin;

        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): static
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    public function getUserRegistered(): ?\DateTime
    {
        return $this->userRegistered;
    }

    public function setUserRegistered(\DateTime $userRegistered): static
    {
        $this->userRegistered = $userRegistered;

        return $this;
    }

    /**
     * @return Collection<int, UserMeta>
     */
    public function getMetas(): Collection
    {
        return $this->metas;
    }

    public function addMeta(UserMeta $meta): static
    {
        if (!$this->metas->contains($meta)) {
            $this->metas->add($meta);
            $meta->setUser($this);
        }

        return $this;
    }

    public function removeMeta(UserMeta $meta): static
    {
        if ($this->metas->removeElement($meta)) {
            // set the owning side to null (unless already changed)
            if ($meta->getUser() === $this) {
                $meta->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }
}
