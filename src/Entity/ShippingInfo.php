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

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "Le prénom est trop long.")]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "Le nom est trop long.")]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    #[Assert\Length(max: 255, maxMessage: "L'adresse est trop longue.")]
    private ?string $address = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "La ville est obligatoire.")]
    #[Assert\Length(max: 100, maxMessage: "Le nom de la ville est trop long.")]
    private ?string $city = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le code postal est obligatoire.")]
    #[Assert\Regex(
        pattern: "/^[0-9A-Za-z\s-]{3,10}$/", 
        message: "Format de code postal invalide."
    )]
    #[Assert\Length(max: 20)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le pays est obligatoire.")]
    #[Assert\Length(max: 100)]
    private ?string $country = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: "Le numéro de téléphone est trop long.")]
    #[Assert\Regex(
        pattern: "/^[\d\s-]{5,20}$/", 
        message: "Format de téléphone invalide."
    )]
    private ?string $phone = null;

    // --- Getters / Setters ---
    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(Order $order): self { $this->order = $order; return $this; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(string $address): self { $this->address = $address; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(string $city): self { $this->city = $city; return $this; }

    public function getPostalCode(): ?string { return $this->postalCode; }
    // Note: Le type hint ici ne doit pas inclure ? si vous avez Assert\NotBlank
    public function setPostalCode(string $postalCode): self { $this->postalCode = $postalCode; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(string $country): self { $this->country = $country; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
}