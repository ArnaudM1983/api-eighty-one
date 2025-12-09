<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cartToken = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalWeight = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'created';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: Payment::class, cascade: ['persist', 'remove'])]
    private Collection $payments;

    #[ORM\OneToOne(mappedBy: 'order', targetEntity: ShippingInfo::class, cascade: ['persist', 'remove'])]
    private ?ShippingInfo $shippingInfo = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // --- Getters / Setters ---
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCartToken(): ?string
    {
        return $this->cartToken;
    }
    public function setCartToken(?string $cartToken): self
    {
        $this->cartToken = $cartToken;
        return $this;
    }

    public function getTotal(): float
    {
        return (float) array_sum(array_map(fn($item) => $item->getTotalPrice(), $this->items->toArray()));
    }

    public function setTotal(float $total): self
    {
        $this->total = $total;
        return $this;
    }


    public function getTotalQuantity(): int
    {
        return array_sum(array_map(fn($item) => $item->getQuantity(), $this->items->toArray()));
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // --- Items ---
    public function getItems(): Collection
    {
        return $this->items;
    }
    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setOrder($this);
        }
        return $this;
    }
    public function removeItem(OrderItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) $item->setOrder(null);
        }
        return $this;
    }

    // --- Payments ---
    public function getPayments(): Collection
    {
        return $this->payments;
    }
    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments[] = $payment;
            $payment->setOrder($this);
        }
        return $this;
    }
    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getOrder() === $this) $payment->setOrder(null);
        }
        return $this;
    }

    // --- Shipping Info ---
    public function getShippingInfo(): ?ShippingInfo
    {
        return $this->shippingInfo;
    }
    public function setShippingInfo(ShippingInfo $shippingInfo): self
    {
        $this->shippingInfo = $shippingInfo;
        if ($shippingInfo->getOrder() !== $this) {
            $shippingInfo->setOrder($this);
        }
        return $this;
    }

    // --- Payment helpers ---
    public function getTotalPaid(): float
    {
        return array_sum(array_map(fn($p) => $p->isPaid() ? $p->getAmount() : 0, $this->payments->toArray()));
    }

    public function getRemainingAmount(): float
    {
        return max(0, $this->getTotal() - $this->getTotalPaid());
    }

    public function isFullyPaid(): bool
    {
        return $this->getRemainingAmount() <= 0;
    }

    public function getTotalWeight(): ?float
    {
        return $this->totalWeight;
    }
    
    public function setTotalWeight(?float $totalWeight): self
    {
        $this->totalWeight = $totalWeight;
        return $this;
    }
}
