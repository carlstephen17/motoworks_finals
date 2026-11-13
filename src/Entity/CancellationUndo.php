<?php

namespace App\Entity;

use App\Repository\CancellationUndoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CancellationUndoRepository::class)]
#[ORM\Table(name: 'cancellation_undo')]
#[ORM\Index(name: 'idx_cancellation_undo_expiry', columns: ['undoStatus', 'undoExpiresAt'])]
#[ORM\Index(name: 'idx_cancellation_undo_record', columns: ['undoRecordType', 'undoRecordId', 'undoStatus'])]
class CancellationUndo
{
    #[ORM\Id]
    #[ORM\Column(name: 'undoId', length: 48)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'undoUserId', referencedColumnName: 'userId', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'undoRecordType', length: 32)]
    private string $recordType;

    #[ORM\Column(name: 'undoRecordId')]
    private int $recordId;

    #[ORM\Column(name: 'undoPreviousStatus', length: 50)]
    private string $previousStatus;

    #[ORM\Column(name: 'undoExpiresAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'undoStatus', length: 20)]
    private string $status = 'Pending';

    #[ORM\Column(name: 'undoCreatedAt', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        string $recordType,
        int $recordId,
        string $previousStatus,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = bin2hex(random_bytes(24));
        $this->user = $user;
        $this->recordType = $recordType;
        $this->recordId = $recordId;
        $this->previousStatus = $previousStatus;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRecordType(): string
    {
        return $this->recordType;
    }

    public function getRecordId(): int
    {
        return $this->recordId;
    }

    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markUndone(): void
    {
        $this->status = 'Undone';
    }

    public function markFinalized(): void
    {
        $this->status = 'Finalized';
    }
}
