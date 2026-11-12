<?php

namespace App\Entity;

use App\Repository\TestimonialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestimonialRepository::class)]
#[ORM\Table(name: 'testimonial')]
class Testimonial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'testimonialId')]
    private ?int $id = null;

    #[ORM\Column(name: 'testimonialName', length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'testimonialEmail', length: 100)]
    private string $email = '';

    #[ORM\Column(name: 'testimonialSubject', length: 255)]
    private string $subject = '';

    #[ORM\Column(name: 'testimonialMessage', type: 'text')]
    private string $message = '';

    #[ORM\Column(name: 'testimonialStatus', length: 20, nullable: true)]
    private ?string $status = 'pending';

    #[ORM\Column(name: 'testimonialImage', length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'testimonialCreatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $email, string $subject, string $message)
    {
        $this->name = $name;
        $this->email = $email;
        $this->subject = $subject;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMessage(): string
    {
        return $this->message;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
