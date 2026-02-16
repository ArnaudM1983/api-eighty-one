<?php

namespace App\Entity;

use App\Repository\ShippingInfoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShippingInfoRepository::class)]
class ShippingInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'shippingInfo', targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    // --- ADRESSE CLIENT (TOUJOURS UTILISÉE POUR LA FACTURATION) ---

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    private ?string $address = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "La ville est obligatoire.")]
    private ?string $city = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le code postal est obligatoire.")]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le pays est obligatoire.")]
    private ?string $country = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    // --- ADRESSE DU POINT RELAIS (UNIQUEMENT SI MONDIAL RELAY) ---

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $pudoId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pudoName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pudoAddress = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pudoCity = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $pudoPostalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pudoCountry = null;

    // --- Getters / Setters existants ---
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getOrder(): ?Order
    {
        return $this->order;
    }
    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }
    public function getLastName(): ?string
    {
        return $this->lastName;
    }
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }
    public function getAddress(): ?string
    {
        return $this->address;
    }
    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }
    public function getCity(): ?string
    {
        return $this->city;
    }
    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }
    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }
    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }
    public function getCountry(): ?string
    {
        return $this->country;
    }
    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }
    public function getPudoId(): ?string
    {
        return $this->pudoId;
    }
    public function setPudoId(?string $pudoId): self
    {
        $this->pudoId = $pudoId;
        return $this;
    }
    public function getPudoName(): ?string
    {
        return $this->pudoName;
    }
    public function setPudoName(?string $pudoName): self
    {
        $this->pudoName = $pudoName;
        return $this;
    }

    // --- Nouveaux Getters / Setters pour l'adresse du Point Relais ---

    public function getPudoAddress(): ?string
    {
        return $this->pudoAddress;
    }
    public function setPudoAddress(?string $pudoAddress): self
    {
        $this->pudoAddress = $pudoAddress;
        return $this;
    }

    public function getPudoCity(): ?string
    {
        return $this->pudoCity;
    }
    public function setPudoCity(?string $pudoCity): self
    {
        $this->pudoCity = $pudoCity;
        return $this;
    }

    public function getPudoPostalCode(): ?string
    {
        return $this->pudoPostalCode;
    }
    public function setPudoPostalCode(?string $pudoPostalCode): self
    {
        $this->pudoPostalCode = $pudoPostalCode;
        return $this;
    }

    public function getPudoCountry(): ?string
    {
        return $this->pudoCountry;
    }
    public function setPudoCountry(?string $pudoCountry): self
    {
        $this->pudoCountry = $pudoCountry;
        return $this;
    }
}
