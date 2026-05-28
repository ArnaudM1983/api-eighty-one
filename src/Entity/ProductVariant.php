<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(nullable: true)]
    private ?int $stock = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $weight = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $salePrice = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $specialPriceFrom = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $specialPriceTo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $promoText = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attributes = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\ManyToOne(inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    // Getters & Setters

    // ID
    public function getId(): ?int
    {
        return $this->id;
    }

    // Name
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    // SKU
    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    // Price
    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;
        return $this;
    }

    // Stock
    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): self
    {
        $this->stock = $stock;
        return $this;
    }

    // Weight
    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function getSalePrice(): ?string
    {
        return $this->salePrice;
    }

    public function setSalePrice(?string $salePrice): self
    {
        $this->salePrice = $salePrice;
        return $this;
    }

    public function getSpecialPriceFrom(): ?\DateTimeInterface
    {
        return $this->specialPriceFrom;
    }

    public function setSpecialPriceFrom(?\DateTimeInterface $specialPriceFrom): self
    {
        $this->specialPriceFrom = $specialPriceFrom;
        return $this;
    }

    public function getSpecialPriceTo(): ?\DateTimeInterface
    {
        return $this->specialPriceTo;
    }

    public function setSpecialPriceTo(?\DateTimeInterface $specialPriceTo): self
    {
        $this->specialPriceTo = $specialPriceTo;
        return $this;
    }

    public function getPromoText(): ?string
    {
        return $this->promoText;
    }

    public function setPromoText(?string $promoText): self
    {
        $this->promoText = $promoText;
        return $this;
    }

    public function isOnSale(): bool
    {
        if ($this->salePrice === null || $this->price === null) {
            return false;
        }

        if ((float) $this->salePrice >= (float) $this->price) {
            return false;
        }

        $now = new \DateTimeImmutable();
        if ($this->specialPriceFrom !== null && $now < $this->specialPriceFrom) {
            return false;
        }
        if ($this->specialPriceTo !== null && $now > $this->specialPriceTo) {
            return false;
        }

        return true;
    }

    public function getFinalPrice(): string
    {
        return $this->isOnSale() ? $this->salePrice : ($this->price ?? '0.00');
    }

    // Image
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    // Attributes
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    // Position
    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    // Visibility
    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    // Product (relation ManyToOne)
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }
}
