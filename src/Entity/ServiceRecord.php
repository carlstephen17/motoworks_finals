<?php

namespace App\Entity;

use App\Repository\ServiceRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceRecordRepository::class)]
#[ORM\Table(name: 'service_record')]
class ServiceRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'recordId')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'customerId', referencedColumnName: 'userId', nullable: false, onDelete: 'RESTRICT')]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: Motorcycle::class)]
    #[ORM\JoinColumn(name: 'motorcycleId', referencedColumnName: 'motorcycleId', nullable: true, onDelete: 'SET NULL')]
    private ?Motorcycle $motorcycle = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'serviceId', referencedColumnName: 'serviceId', nullable: false, onDelete: 'RESTRICT')]
    private Service $service;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'createdByStaffId', referencedColumnName: 'userId', nullable: false, onDelete: 'RESTRICT')]
    private User $createdByStaff;

    #[ORM\Column(name: 'recordMotorcycleModel', length: 255)]
    private string $motorcycleModel;

    #[ORM\Column(name: 'recordPlateNumber', length: 32, nullable: true)]
    private ?string $plateNumber;

    #[ORM\Column(name: 'recordServiceDate', type: 'date_immutable')]
    private \DateTimeImmutable $serviceDate;

    #[ORM\Column(name: 'recordWorkDescription', type: 'text')]
    private string $workDescription;

    #[ORM\Column(name: 'recordPartsUsed', type: 'text', nullable: true)]
    private ?string $partsUsed;

    #[ORM\Column(name: 'recordCost', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $cost;

    #[ORM\Column(name: 'recordStatus', length: 50)]
    private string $status = 'In Progress';

    #[ORM\Column(name: 'recordNotes', type: 'text', nullable: true)]
    private ?string $notes;

    #[ORM\Column(name: 'recordCreatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'recordUpdatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $customer,
        Service $service,
        User $createdByStaff,
        string $motorcycleModel,
        \DateTimeImmutable $serviceDate,
        string $workDescription,
        ?string $plateNumber = null,
        ?string $partsUsed = null,
        ?string $cost = null,
        ?string $notes = null,
    ) {
        $this->customer = $customer;
        $this->service = $service;
        $this->createdByStaff = $createdByStaff;
        $this->motorcycleModel = $motorcycleModel;
        $this->serviceDate = $serviceDate;
        $this->workDescription = $workDescription;
        $this->plateNumber = $plateNumber;
        $this->partsUsed = $partsUsed;
        $this->cost = $cost;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): User
    {
        return $this->customer;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function setMotorcycle(?Motorcycle $motorcycle): void
    {
        $this->motorcycle = $motorcycle;
        if ($motorcycle !== null) {
            $this->motorcycleModel = $motorcycle->getModel();
            $this->plateNumber = $motorcycle->getPlateNumber();
        }
        $this->touch();
    }

    public function setService(Service $service): void
    {
        $this->service = $service;
        $this->touch();
    }

    public function getCreatedByStaff(): User
    {
        return $this->createdByStaff;
    }

    public function getMotorcycleModel(): string
    {
        return $this->motorcycleModel;
    }

    public function getPlateNumber(): ?string
    {
        return $this->plateNumber;
    }

    public function getServiceDate(): \DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function getWorkDescription(): string
    {
        return $this->workDescription;
    }

    public function setServiceDate(\DateTimeImmutable $serviceDate): void
    {
        $this->serviceDate = $serviceDate;
        $this->touch();
    }

    public function setWorkDescription(string $workDescription): void
    {
        $this->workDescription = $workDescription;
        $this->touch();
    }

    public function getPartsUsed(): ?string
    {
        return $this->partsUsed;
    }

    public function setPartsUsed(?string $partsUsed): void
    {
        $this->partsUsed = $partsUsed;
        $this->touch();
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(?string $cost): void
    {
        $this->cost = $cost;
        $this->touch();
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
