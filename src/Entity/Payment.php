<?php
namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'pending'; // pending, success, failed, refunded

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $method = null; // ex: stripe, paypal, bank_transfer

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amount = null; // montant payé

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null; // ID du prestataire de paiement

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct() {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $order): self { $this->order = $order; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { 
        $this->status = $status; 
        $this->updatedAt = new \DateTimeImmutable();
        return $this; 
    }

    public function getMethod(): ?string { return $this->method; }
    public function setMethod(?string $method): self { $this->method = $method; return $this; }

    public function getAmount(): float { return (float)$this->amount; }
    public function setAmount(string $amount): self { $this->amount = $amount; return $this; }

    public function getTransactionId(): ?string { return $this->transactionId; }
    public function setTransactionId(?string $transactionId): self { $this->transactionId = $transactionId; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    // --- Méthodes ---
    public function isPaid(): bool {
        return $this->status === 'success';
    }

    public function isPending(): bool {
        return $this->status === 'pending';
    }

    public function isFailed(): bool {
        return $this->status === 'failed';
    }

    public function getRemainingAmount(): float {
        if (!$this->order) return 0;
        $totalPaid = $this->order->getPayments()->filter(fn($p) => $p->isPaid())
                                        ->map(fn($p) => $p->getAmount())
                                        ->sum();
        return max(0, $this->order->getTotal() - $totalPaid);
    }
}
