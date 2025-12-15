<?php

namespace App\Entity;

use App\Repository\ShippingTariffRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingTariffRepository::class)]
class ShippingTariff
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2)]
    private ?string $countryCode = null;

    // Type de livraison ('pr' pour Point Relais, 'locker' pour Locker)
    #[ORM\Column(length: 20)]
    private ?string $modeCode = null;

    // Poids maximal en grammes pour ce tarif (le palier)
    #[ORM\Column]
    private ?int $weightMaxG = null;

    // Prix Hors Taxes du tarif
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $priceHt = null;
    
    // --- Getters / Setters ---
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getModeCode(): ?string
    {
        return $this->modeCode;
    }

    public function setModeCode(string $modeCode): self
    {
        $this->modeCode = $modeCode;
        return $this;
    }

    public function getWeightMaxG(): ?int
    {
        return $this->weightMaxG;
    }

    public function setWeightMaxG(int $weightMaxG): self
    {
        $this->weightMaxG = $weightMaxG;
        return $this;
    }

    public function getPriceHt(): ?string
    {
        return $this->priceHt;
    }

    public function setPriceHt(string $priceHt): self
    {
        $this->priceHt = $priceHt;
        return $this;
    }
}