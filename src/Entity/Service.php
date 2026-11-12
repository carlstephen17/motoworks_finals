<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'service')]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'serviceId')]
    private ?int $id = null;

    #[ORM\Column(name: 'serviceName', length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'serviceImage', length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'serviceDescription', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'servicePrice', type: 'decimal', precision: 10, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(name: 'serviceStatus', length: 50, nullable: true)]
    private ?string $status = 'Available';

    #[ORM\Column(name: 'serviceActive', type: 'boolean', nullable: true)]
    private ?bool $active = true;

    #[ORM\Column(name: 'serviceFeatured', type: 'boolean', nullable: true)]
    private ?bool $featured = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function setFeatured(?bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function isFeatured(): ?bool
    {
        return $this->featured;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
