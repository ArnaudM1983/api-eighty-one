<?php

namespace App\Entity;

use App\Repository\GuideRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GuideRepository::class)]
class Guide
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: Types::JSON)]
    private array $switcher = [];

    #[ORM\Column(type: Types::JSON)]
    private array $faq = [];

    #[ORM\Column(type: Types::JSON)]
    private array $expertChoiceSlugs = [];

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        
        $this->switcher = [
            'standard' => ['title' => '', 'points' => []],
            'expert' => ['title' => '', 'points' => []]
        ];
    }

    // --- ID ---
    public function getId(): ?int
    {
        return $this->id;
    }

    // --- TITLE ---
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    // --- SLUG ---
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    // --- DESCRIPTION ---
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    // --- IMAGE ---
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    // --- CONTENT ---
    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    // --- SWITCHER ---
    public function getSwitcher(): array
    {
        return $this->switcher;
    }

    public function setSwitcher(array $switcher): self
    {
        $this->switcher = $switcher;
        return $this;
    }

    // --- FAQ ---
    public function getFaq(): array
    {
        return $this->faq;
    }

    public function setFaq(array $faq): self
    {
        $this->faq = $faq;
        return $this;
    }

    // --- EXPERT CHOICE SLUGS ---
    public function getExpertChoiceSlugs(): array
    {
        return $this->expertChoiceSlugs;
    }

    public function setExpertChoiceSlugs(array $expertChoiceSlugs): self
    {
        $this->expertChoiceSlugs = $expertChoiceSlugs;
        return $this;
    }

    //  --- POSITION ---
    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    // --- TIMESTAMPS ---
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
}