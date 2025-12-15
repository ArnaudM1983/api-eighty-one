<?php

namespace App\Entity;

use App\Repository\CartItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cart $cart = null;

    #[ORM\ManyToOne]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    private ?ProductVariant $variant = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $weight = null;

    public function getId(): ?int { return $this->id; }
    public function getCart(): ?Cart { return $this->cart; }
    public function setCart(?Cart $cart): self { $this->cart = $cart; return $this; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }
    public function getVariant(): ?ProductVariant { return $this->variant; }
    public function setVariant(?ProductVariant $variant): self { $this->variant = $variant; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }
    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): self { $this->price = $price; return $this; }
    public function getWeight(): ?float { return $this->weight; }
    public function setWeight(?float $weight): self { $this->weight = $weight; return $this; }
    public function getTotalWeight(): float
    {
        return ($this->weight ?? 0.0) * $this->quantity;
    }
}
