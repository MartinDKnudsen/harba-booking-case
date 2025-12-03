<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/**
 *
 */
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'booking', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_provider_start', columns: ['provider_id', 'start_at'])
])]
class Booking
{
    /**
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var User|null
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?User $user = null;

    /**
     * @var Provider|null
     */
    #[ORM\ManyToOne(targetEntity: Provider::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Provider $provider = null;

    /**
     * @var Service|null
     */
    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Service $service = null;

    /**
     * @var \DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    #[Assert\Type(\DateTimeImmutable::class)]
    private \DateTimeImmutable $startAt;

    /**
     * @var \DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    #[Assert\Type(\DateTimeImmutable::class)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var \DateTimeImmutable|null
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Assert\Type(\DateTimeImmutable::class)]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * @var \DateTimeImmutable|null
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Assert\Type(\DateTimeImmutable::class)]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @var string|null
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    /**
     *
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Provider|null
     */
    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    /**
     * @param Provider $provider
     * @return $this
     */
    public function setProvider(Provider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @return Service|null
     */
    public function getService(): ?Service
    {
        return $this->service;
    }

    /**
     * @param Service $service
     * @return $this
     */
    public function setService(Service $service): self
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    /**
     * @param \DateTimeImmutable $startAt
     * @return $this
     */
    public function setStartAt(\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    /**
     * @return void
     */
    public function cancel(): void
    {
        if ($this->cancelledAt === null) {
            $this->cancelledAt = new \DateTimeImmutable();
        }
    }

    /**
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * @return void
     */
    public function softDelete(): void
    {
        if ($this->deletedAt === null) {
            $this->deletedAt = new \DateTimeImmutable();
        }
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @return string|null
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @param string|null $note
     * @return $this
     */
    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }
}
