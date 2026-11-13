<?php

namespace App\Entity;

use App\Repository\ServiceBookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceBookingRepository::class)]
#[ORM\Table(name: 'service_booking')]
class ServiceBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'bookingId')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userId', referencedColumnName: 'userId', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'serviceId', referencedColumnName: 'serviceId', nullable: false, onDelete: 'CASCADE')]
    private Service $service;

    #[ORM\Column(name: 'bookingVehicleModel', length: 255, nullable: true)]
    private ?string $vehicleModel = null;

    #[ORM\Column(name: 'bookingDate', type: 'date_immutable')]
    private \DateTimeImmutable $bookingDate;

    #[ORM\Column(name: 'bookingTime', type: 'time_immutable')]
    private \DateTimeImmutable $bookingTime;

    #[ORM\Column(name: 'bookingBundleSlug', length: 100, nullable: true)]
    private ?string $bundleSlug = null;

    #[ORM\Column(name: 'bookingBundleOption', length: 255, nullable: true)]
    private ?string $bundleOption = null;

    #[ORM\Column(name: 'bookingStatus', length: 50, nullable: true)]
    private ?string $status = 'Pending';

    #[ORM\Column(name: 'bookingNotes', type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'bookingRejectionReason', type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(name: 'bookingSource', length: 32)]
    private string $source = 'customer_online';

    #[ORM\ManyToOne(targetEntity: Motorcycle::class)]
    #[ORM\JoinColumn(name: 'bookingMotorcycleId', referencedColumnName: 'motorcycleId', nullable: true, onDelete: 'SET NULL')]
    private ?Motorcycle $motorcycle = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'createdByStaffId', referencedColumnName: 'userId', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByStaff = null;

    #[ORM\Column(name: 'bookingCreatedAt', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        Service $service,
        string $vehicleModel,
        \DateTimeImmutable $bookingDate,
        \DateTimeImmutable $bookingTime,
        ?string $bundleSlug,
        ?string $bundleOption,
        ?string $notes,
    ) {
        $this->user = $user;
        $this->service = $service;
        $this->vehicleModel = $vehicleModel;
        $this->bookingDate = $bookingDate;
        $this->bookingTime = $bookingTime;
        $this->bundleSlug = $bundleSlug;
        $this->bundleOption = $bundleOption;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function setService(Service $service): void
    {
        $this->service = $service;
    }

    public function getVehicleModel(): ?string
    {
        return $this->vehicleModel;
    }

    public function getMotorcycle(): ?Motorcycle
    {
        return $this->motorcycle;
    }

    public function setMotorcycle(?Motorcycle $motorcycle): void
    {
        $this->motorcycle = $motorcycle;
    }

    public function setVehicleModel(?string $vehicleModel): void
    {
        $this->vehicleModel = $vehicleModel;
    }

    public function getBookingDate(): \DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function getBookingTime(): \DateTimeImmutable
    {
        return $this->bookingTime;
    }

    public function getBundleSlug(): ?string
    {
        return $this->bundleSlug;
    }

    public function getBundleOption(): ?string
    {
        return $this->bundleOption;
    }

    public function setBundleOption(?string $bundleOption): void
    {
        $this->bundleOption = $bundleOption;
    }

    public function setBookingDate(\DateTimeImmutable $bookingDate): void
    {
        $this->bookingDate = $bookingDate;
    }

    public function setBookingTime(\DateTimeImmutable $bookingTime): void
    {
        $this->bookingTime = $bookingTime;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function setSource(string $source): void
    {
        if (!in_array($source, ['customer_online', 'staff', 'admin'], true)) {
            throw new \InvalidArgumentException('Unsupported booking source.');
        }

        $this->source = $source;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setCreatedByStaff(User $staff): void
    {
        $this->createdByStaff = $staff;
    }

    public function getCreatedByStaff(): ?User
    {
        return $this->createdByStaff;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): void
    {
        $this->rejectionReason = $rejectionReason;
    }
}
