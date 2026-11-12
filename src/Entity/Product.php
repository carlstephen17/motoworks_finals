<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'productId')]
    private ?int $id = null;

    #[ORM\Column(name: 'productName', length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'productPrice', type: 'decimal', precision: 10, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(name: 'productStock')]
    private int $stock = 0;

    #[ORM\Column(name: 'productImage', length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'productActive', type: 'boolean', nullable: true)]
    private ?bool $active = true;

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

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

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

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
