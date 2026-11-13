<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\OrderItemRepository::class)]
#[ORM\Table(name: '`order`')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'orderId')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userId', referencedColumnName: 'userId', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'productId', referencedColumnName: 'productId', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(name: 'orderQuantity')]
    private int $quantity;

    #[ORM\Column(name: 'orderTotalAmount', type: 'decimal', precision: 10, scale: 2)]
    private string $totalAmount;

    #[ORM\Column(name: 'orderPaymentMethod', length: 50, nullable: true)]
    private ?string $paymentMethod;

    #[ORM\Column(name: 'orderPaymentReference', length: 32, nullable: true)]
    private ?string $paymentReference;

    #[ORM\Column(name: 'orderStatus', length: 50, nullable: true)]
    private ?string $status = 'Pending';

    #[ORM\Column(name: 'orderCreatedAt', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: CustomerOrder::class)]
    #[ORM\JoinColumn(name: 'orderGroupId', referencedColumnName: 'orderGroupId', nullable: true, onDelete: 'RESTRICT')]
    private ?CustomerOrder $customerOrder;

    #[ORM\Column(name: 'orderUnitPrice', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $unitPrice;

    public function __construct(
        User $user,
        Product $product,
        int $quantity,
        string $totalAmount,
        string $paymentMethod,
        string $paymentReference,
        CustomerOrder $customerOrder,
        string $unitPrice,
    ) {
        $this->user = $user;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->totalAmount = $totalAmount;
        $this->paymentMethod = $paymentMethod;
        $this->paymentReference = $paymentReference;
        $this->customerOrder = $customerOrder;
        $this->unitPrice = $unitPrice;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCustomerOrder(): ?CustomerOrder
    {
        return $this->customerOrder;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
