<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InstagramToken
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\Column(type:"datetime")]
    private \DateTimeInterface $expiresAt;

    public function getId(): ?int { return $this->id; }
    // public function getToken(): string { return $this->token; }
    // public function setToken(string $token): self { $this->token = $token; return $this; }
    public function getExpiresAt(): \DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeInterface $expiresAt): self { $this->expiresAt = $expiresAt; return $this; }
}
