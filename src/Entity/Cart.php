<?php

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cart (Shopping cart)
 * 
 * Represents a customer's shopping session.
 * Linked to a browser through a unique token stored in a cookie.
 * Contains CartItems and total calculation helpers.
 */

#[ORM\Entity(repositoryClass: CartRepository::class)]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Unique token stored in cookie to identify the cart
     */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $token = null;

    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Getter / Setter */
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getToken(): ?string
    {
        return $this->token;
    }
    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Add a product to the cart
     */
    public function addItem(CartItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setCart($this);
        }
        return $this;
    }

    /**
     * Remove a single CartItem
     */
    public function removeItem(CartItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getCart() === $this) $item->setCart(null);
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Total quantity of all items
     */
    public function getTotalQuantity(): int
    {
        if (!$this->items) return 0;

        return array_sum(
            array_map(fn($item) => $item->getQuantity(), $this->items->toArray())
        );
    }

    /**
     * Total price of cart (sum of all item.price * quantity)
     */
    public function getTotalPrice(): float
    {
        if (!$this->items) return 0;

        return array_sum(
            array_map(fn($item) => $item->getPrice() * $item->getQuantity(), $this->items->toArray())
        );
    }

    /**
     * Total weight of all items in the cart (sum of all item.weight * quantity)
     */
    public function getTotalWeight(): float
    {
        if (!$this->items) return 0.0;

        return array_sum(
            array_map(fn($item) => $item->getTotalWeight(), $this->items->toArray())
        );
    }
}
