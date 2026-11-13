<?php

namespace App\Entity;

use App\Repository\PromoClaimRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromoClaimRepository::class)]
#[ORM\Table(name: 'promo_claim')]
class PromoClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'claimId')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userId', referencedColumnName: 'userId', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'claimFullName', length: 255)]
    private string $fullName;

    #[ORM\Column(name: 'claimPhone', length: 50)]
    private string $phone;

    #[ORM\Column(name: 'claimBundleSlug', length: 100)]
    private string $bundleSlug;

    #[ORM\ManyToOne(targetEntity: Promo::class)]
    #[ORM\JoinColumn(name: 'claimPromoId', referencedColumnName: 'promoId', nullable: true, onDelete: 'SET NULL')]
    private ?Promo $promo = null;

    #[ORM\Column(name: 'claimSelectedOption', length: 255)]
    private string $selectedOption;

    #[ORM\Column(name: 'claimAppointmentDate', type: 'date_immutable')]
    private \DateTimeImmutable $appointmentDate;

    #[ORM\Column(name: 'claimAppointmentTime', type: 'time_immutable')]
    private \DateTimeImmutable $appointmentTime;

    #[ORM\Column(name: 'claimStatus', length: 50, nullable: true)]
    private ?string $status = 'Pending';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'claimCreatedByStaffId', referencedColumnName: 'userId', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByStaff = null;

    #[ORM\Column(name: 'claimCreatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        ?User $user,
        string $fullName,
        string $phone,
        string $bundleSlug,
        string $selectedOption,
        \DateTimeImmutable $appointmentDate,
        \DateTimeImmutable $appointmentTime,
    ) {
        $this->user = $user;
        $this->fullName = $fullName;
        $this->phone = $phone;
        $this->bundleSlug = $bundleSlug;
        $this->selectedOption = $selectedOption;
        $this->appointmentDate = $appointmentDate;
        $this->appointmentTime = $appointmentTime;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getBundleSlug(): string
    {
        return $this->bundleSlug;
    }

    public function getPromo(): ?Promo
    {
        return $this->promo;
    }

    public function setPromo(?Promo $promo): void
    {
        $this->promo = $promo;
        if ($promo !== null) {
            $this->bundleSlug = $promo->getSlug();
        }
    }

    public function setBundleSlug(string $bundleSlug): void
    {
        $this->bundleSlug = $bundleSlug;
    }

    public function getSelectedOption(): string
    {
        return $this->selectedOption;
    }

    public function getAppointmentDate(): \DateTimeImmutable
    {
        return $this->appointmentDate;
    }

    public function getAppointmentTime(): \DateTimeImmutable
    {
        return $this->appointmentTime;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function setCreatedByStaff(User $staff): void
    {
        $this->createdByStaff = $staff;
    }

    public function getCreatedByStaff(): ?User
    {
        return $this->createdByStaff;
    }

    public function setSelectedOption(string $selectedOption): void
    {
        $this->selectedOption = $selectedOption;
    }

    public function setAppointmentDate(\DateTimeImmutable $appointmentDate): void
    {
        $this->appointmentDate = $appointmentDate;
    }

    public function setAppointmentTime(\DateTimeImmutable $appointmentTime): void
    {
        $this->appointmentTime = $appointmentTime;
    }
}
