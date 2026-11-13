<?php

namespace App\Entity;

use App\Repository\CustomerOrderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerOrderRepository::class)]
#[ORM\Table(name: 'customer_order')]
class CustomerOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'orderGroupId')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userId', referencedColumnName: 'userId', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(name: 'orderDeliveryAddress', type: 'text', nullable: true)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(name: 'orderPaymentMethod', length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(name: 'orderPaymentReference', length: 32, nullable: true)]
    private ?string $paymentReference = null;

    #[ORM\Column(name: 'orderSubmissionKey', length: 36, nullable: true, unique: true)]
    private ?string $submissionKey = null;

    #[ORM\Column(name: 'orderPaymentStatus', length: 32)]
    private string $paymentStatus = 'Pending';

    #[ORM\Column(name: 'orderPaymentVerifiedAt', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paymentVerifiedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'orderPaymentVerifiedById', referencedColumnName: 'userId', nullable: true, onDelete: 'SET NULL')]
    private ?User $paymentVerifiedBy = null;

    #[ORM\Column(name: 'orderStatus', length: 50)]
    private string $status = 'Pending';

    #[ORM\Column(name: 'orderCancellationReason', type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'orderCreatedByStaffId', referencedColumnName: 'userId', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByStaff = null;

    #[ORM\Column(name: 'orderCreatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        string $address,
        string $paymentMethod,
        string $paymentReference,
        ?string $submissionKey = null,
    )
    {
        $this->user = $user;
        $this->deliveryAddress = $address;
        $this->paymentMethod = $paymentMethod;
        $this->paymentReference = $paymentReference;
        $this->submissionKey = $submissionKey;
        $this->paymentStatus = $paymentMethod === 'Cash on Delivery' ? 'Due on delivery' : 'Pending';
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(string $paymentReference): void
    {
        $this->paymentReference = $paymentReference;
    }

    public function getSubmissionKey(): ?string
    {
        return $this->submissionKey;
    }

    public function getPaymentVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->paymentVerifiedAt;
    }

    public function getPaymentVerifiedBy(): ?User
    {
        return $this->paymentVerifiedBy;
    }

    public function markPaymentVerified(User $staff): void
    {
        if ($this->paymentStatus !== 'Pending') {
            throw new \DomainException('Only pending payments can be confirmed.');
        }

        $this->paymentStatus = 'Paid';
        $this->paymentVerifiedAt = new \DateTimeImmutable();
        $this->paymentVerifiedBy = $staff;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?string $deliveryAddress): void
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
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

    public function setCancellationReason(?string $reason): void
    {
        $this->cancellationReason = $reason;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
