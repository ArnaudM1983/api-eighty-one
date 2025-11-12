<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CustomOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $orderId = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $postDate;

    #[ORM\Column(type: "string", length: 50)]
    private string $postStatus;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $postExcerpt = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $postTitle;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "orders")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: true)]
    private ?User $user = null;

    // Getters and Setters

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getPostDate(): ?\DateTime
    {
        return $this->postDate;
    }

    public function setPostDate(\DateTime $postDate): static
    {
        $this->postDate = $postDate;

        return $this;
    }

    public function getPostStatus(): ?string
    {
        return $this->postStatus;
    }

    public function setPostStatus(string $postStatus): static
    {
        $this->postStatus = $postStatus;

        return $this;
    }

    public function getPostExcerpt(): ?string
    {
        return $this->postExcerpt;
    }

    public function setPostExcerpt(?string $postExcerpt): static
    {
        $this->postExcerpt = $postExcerpt;

        return $this;
    }

    public function getPostTitle(): ?string
    {
        return $this->postTitle;
    }

    public function setPostTitle(string $postTitle): static
    {
        $this->postTitle = $postTitle;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
