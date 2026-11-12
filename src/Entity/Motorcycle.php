<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'motorcycle')]
class Motorcycle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'motorcycleId')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userId', referencedColumnName: 'userId', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(name: 'motorcycleModel', length: 255)]
    private string $model = '';

    #[ORM\Column(name: 'motorcyclePlateNumber', length: 32, nullable: true, unique: true)]
    private ?string $plateNumber = null;

    #[ORM\Column(name: 'motorcycleCreatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $model, ?string $plateNumber = null)
    {
        $this->user = $user;
        $this->model = $model;
        $this->plateNumber = $plateNumber;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getPlateNumber(): ?string
    {
        return $this->plateNumber;
    }
}
