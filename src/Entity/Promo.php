<?php

namespace App\Entity;

use App\Repository\PromoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromoRepository::class)]
#[ORM\Table(name: 'promo')]
class Promo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'promoId')]
    private ?int $id = null;

    #[ORM\Column(name: 'promoSlug', length: 100, unique: true)]
    private string $slug = '';

    #[ORM\Column(name: 'promoTitle', length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'promoBadge', length: 50)]
    private string $badge = '';

    #[ORM\Column(name: 'promoDescription', type: 'text')]
    private string $description = '';

    #[ORM\Column(name: 'promoItemLabel', length: 255)]
    private string $itemLabel = '';

    #[ORM\Column(name: 'promoItemOptions', type: 'text')]
    private string $itemOptions = '';

    #[ORM\Column(name: 'promoPerks', type: 'text', nullable: true)]
    private ?string $perks = null;

    #[ORM\Column(name: 'promoProductImage', length: 255, nullable: true)]
    private ?string $productImage = null;

    #[ORM\Column(name: 'promoServiceImage', length: 255, nullable: true)]
    private ?string $serviceImage = null;

    #[ORM\Column(name: 'promoProductIcon', length: 100, nullable: true)]
    private ?string $productIcon = null;

    #[ORM\Column(name: 'promoServiceIcon', length: 100, nullable: true)]
    private ?string $serviceIcon = null;

    #[ORM\Column(name: 'promoActive', type: 'boolean')]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'promoCreatedByStaffId', referencedColumnName: 'userId', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdByStaff = null;

    #[ORM\Column(name: 'promoCreatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBadge(): string
    {
        return $this->badge;
    }

    public function setBadge(string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getItemLabel(): string
    {
        return $this->itemLabel;
    }

    public function setItemLabel(string $itemLabel): static
    {
        $this->itemLabel = $itemLabel;

        return $this;
    }

    public function getItemOptions(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->itemOptions) ?: [])));
    }

    public function setItemOptions(string $itemOptions): static
    {
        $this->itemOptions = $itemOptions;

        return $this;
    }

    public function getItemOptionsText(): string
    {
        return $this->itemOptions;
    }

    public function getPerks(): ?string
    {
        return $this->perks;
    }

    public function setPerks(?string $perks): static
    {
        $this->perks = $perks;

        return $this;
    }

    public function getProductImage(): ?string
    {
        return $this->productImage;
    }

    public function setProductImage(?string $productImage): static
    {
        $this->productImage = $productImage;

        return $this;
    }

    public function getServiceImage(): ?string
    {
        return $this->serviceImage;
    }

    public function setServiceImage(?string $serviceImage): static
    {
        $this->serviceImage = $serviceImage;

        return $this;
    }

    public function getProductIcon(): ?string
    {
        return $this->productIcon ?: 'fa-solid fa-circle-dot';
    }

    public function getStoredProductIcon(): ?string
    {
        return $this->productIcon;
    }

    public function setProductIcon(?string $productIcon): static
    {
        $this->productIcon = $productIcon;

        return $this;
    }

    public function getServiceIcon(): ?string
    {
        return $this->serviceIcon ?: 'fa-solid fa-screwdriver-wrench';
    }

    public function getStoredServiceIcon(): ?string
    {
        return $this->serviceIcon;
    }

    public function setServiceIcon(?string $serviceIcon): static
    {
        $this->serviceIcon = $serviceIcon;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPerksList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->perks ?? '') ?: [])));
    }

    public function getCreatedByStaff(): ?User
    {
        return $this->createdByStaff;
    }

    public function setCreatedByStaff(?User $createdByStaff): static
    {
        $this->createdByStaff = $createdByStaff;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
