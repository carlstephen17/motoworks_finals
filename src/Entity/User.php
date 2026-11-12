<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'userId')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $username = '';

    #[ORM\Column(name: 'userFirstName', length: 50)]
    private string $firstName = '';

    #[ORM\Column(name: 'userLastName', length: 50)]
    private string $lastName = '';

    #[ORM\Column(name: 'userEmail', length: 100, unique: true)]
    private string $email = '';

    #[ORM\Column(name: 'userPhone', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'userAddress', type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(name: 'userPassword', length: 255)]
    private string $password = '';

    #[ORM\Column(name: 'userRole', length: 20, options: ['default' => 'customer'])]
    private string $role = 'customer';

    #[ORM\Column(name: 'userRememberToken', length: 255, nullable: true)]
    private ?string $rememberToken = null;

    #[ORM\Column(name: 'userResetToken', length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(name: 'userResetExpiry', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resetExpiry = null;

    #[ORM\Column(name: 'userAvatar', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(name: 'userCreatedAt', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return match ($this->role) {
            'admin' => ['ROLE_ADMIN', 'ROLE_USER'],
            'staff' => ['ROLE_STAFF', 'ROLE_USER'],
            default => ['ROLE_USER'],
        };
    }

    public function setRole(string $role): static
    {
        if (!in_array($role, ['admin', 'staff', 'customer'], true)) {
            throw new \InvalidArgumentException('Unsupported user role.');
        }

        $this->role = $role;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $rememberToken): static
    {
        $this->rememberToken = $rememberToken;

        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getResetExpiry(): ?\DateTimeInterface
    {
        return $this->resetExpiry;
    }

    public function setResetExpiry(?\DateTimeInterface $resetExpiry): static
    {
        $this->resetExpiry = $resetExpiry;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function eraseCredentials(): void
    {
    }
}
