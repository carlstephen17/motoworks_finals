<?php

namespace App\Service;

use App\Entity\CancellationUndo;
use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\PromoClaim;
use App\Entity\ServiceBooking;
use App\Entity\User;
use App\Repository\CancellationUndoRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class CancellationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CancellationUndoRepository $cancellations,
        private readonly OrderStatusManager $orderStatusManager,
    ) {
    }

    public function beginOrderCancellation(OrderItem $item, User $user): CancellationUndo
    {
        $this->finalizeExpired();
        if ($item->getUser()->getId() === null || $item->getUser()->getId() !== $user->getId()) {
            throw new \DomainException('Order not found.');
        }

        $group = $item->getCustomerOrder();
        $recordType = $group instanceof CustomerOrder ? 'customer_order' : 'order_item';
        $recordId = $group instanceof CustomerOrder ? $group->getId() : $item->getId();
        if ($recordId === null) {
            throw new \DomainException('Order not found.');
        }

        return $this->transactional(function () use ($item, $group, $user, $recordType, $recordId): CancellationUndo {
            $this->entityManager->lock($group ?? $item, LockMode::PESSIMISTIC_WRITE);
            $existing = $this->cancellations->findPendingForRecord($recordType, $recordId);
            if ($existing instanceof CancellationUndo) {
                return $existing;
            }

            $this->orderStatusManager->markCancellationPending($group ?? $item);
            $action = $this->newAction($user, $recordType, $recordId, 'Pending');
            $this->entityManager->persist($action);

            return $action;
        });
    }

    public function beginAppointmentCancellation(ServiceBooking $booking, User $user): CancellationUndo
    {
        $this->finalizeExpired();
        if ($booking->getUser()->getId() === null || $booking->getUser()->getId() !== $user->getId()) {
            throw new \DomainException('Appointment not found.');
        }

        $id = $booking->getId();
        if ($id === null) {
            throw new \DomainException('Appointment not found.');
        }

        return $this->beginStatusCancellation(
            'service_booking',
            $id,
            $user,
            $booking,
            $booking->getStatus(),
            static fn () => $booking->setStatus('Cancellation Pending'),
        );
    }

    public function beginPromoClaimCancellation(PromoClaim $claim, User $user): CancellationUndo
    {
        $this->finalizeExpired();
        if ($claim->getUser()?->getId() === null || $claim->getUser()?->getId() !== $user->getId()) {
            throw new \DomainException('Promotion claim not found.');
        }

        $id = $claim->getId();
        if ($id === null) {
            throw new \DomainException('Promotion claim not found.');
        }

        return $this->beginStatusCancellation(
            'promo_claim',
            $id,
            $user,
            $claim,
            $claim->getStatus(),
            static fn () => $claim->setStatus('Cancellation Pending'),
        );
    }

    public function undo(string $id, User $user): bool
    {
        return $this->transactional(function () use ($id, $user): bool {
            $action = $this->lockAction($id);
            if ($action->getUser()->getId() !== $user->getId()) {
                throw new \DomainException('Cancellation action not found.');
            }
            if ($action->getStatus() !== 'Pending') {
                throw new \DomainException('This cancellation can no longer be undone.');
            }

            if ($action->getExpiresAt() <= new \DateTimeImmutable()) {
                $this->finalizeAction($action);

                return false;
            }

            $this->restoreRecord($action);
            $action->markUndone();

            return true;
        });
    }

    public function finalize(string $id, ?User $user = null): bool
    {
        return $this->transactional(function () use ($id, $user): bool {
            $action = $this->lockAction($id);
            if ($user !== null && $action->getUser()->getId() !== $user->getId()) {
                throw new \DomainException('Cancellation action not found.');
            }
            if ($action->getStatus() !== 'Pending') {
                return false;
            }
            if ($action->getExpiresAt() > new \DateTimeImmutable()) {
                return false;
            }

            $this->finalizeAction($action);

            return true;
        });
    }

    public function finalizeExpired(): void
    {
        foreach ($this->cancellations->findExpiredIds(new \DateTimeImmutable()) as $id) {
            $this->finalize((string) $id);
        }
    }

    /** @return array<string, CancellationUndo> */
    public function findPendingByRecordForUser(User $user): array
    {
        $this->finalizeExpired();
        $pending = [];
        foreach ($this->cancellations->findPendingForUser($user) as $action) {
            $pending[$action->getRecordType().':'.$action->getRecordId()] = $action;
        }

        return $pending;
    }

    private function beginStatusCancellation(
        string $recordType,
        int $recordId,
        User $user,
        ServiceBooking|PromoClaim $record,
        ?string $previousStatus,
        callable $markPending,
    ): CancellationUndo {
        return $this->transactional(function () use ($recordType, $recordId, $user, $record, $previousStatus, $markPending): CancellationUndo {
            $this->entityManager->lock($record, LockMode::PESSIMISTIC_WRITE);
            $existing = $this->cancellations->findPendingForRecord($recordType, $recordId);
            if ($existing instanceof CancellationUndo) {
                return $existing;
            }
            $previousStatus = trim((string) $previousStatus) ?: 'Pending';
            if (strtolower($previousStatus) !== 'pending') {
                throw new \DomainException('Only pending transactions can be cancelled.');
            }

            $markPending();
            $action = $this->newAction($user, $recordType, $recordId, $previousStatus);
            $this->entityManager->persist($action);

            return $action;
        });
    }

    private function newAction(User $user, string $recordType, int $recordId, string $previousStatus): CancellationUndo
    {
        return new CancellationUndo(
            $user,
            $recordType,
            $recordId,
            $previousStatus,
            (new \DateTimeImmutable())->modify('+3 seconds'),
        );
    }

    private function lockAction(string $id): CancellationUndo
    {
        $action = $this->entityManager->find(CancellationUndo::class, $id, LockMode::PESSIMISTIC_WRITE);
        if (!$action instanceof CancellationUndo) {
            throw new \DomainException('Cancellation action not found.');
        }

        return $action;
    }

    private function restoreRecord(CancellationUndo $action): void
    {
        $record = $this->findRecord($action);
        if ($action->getRecordType() === 'customer_order' || $action->getRecordType() === 'order_item') {
            if (!$record instanceof CustomerOrder && !$record instanceof OrderItem) {
                throw new \DomainException('Order not found.');
            }
            $this->orderStatusManager->restorePendingCancellation($record);

            return;
        }

        if ($record instanceof ServiceBooking || $record instanceof PromoClaim) {
            if ($record->getStatus() !== 'Cancellation Pending') {
                throw new \DomainException('This transaction is no longer awaiting cancellation.');
            }
            $record->setStatus($action->getPreviousStatus());

            return;
        }

        throw new \DomainException('Transaction not found.');
    }

    private function finalizeAction(CancellationUndo $action): void
    {
        $record = $this->findRecord($action);
        if ($action->getRecordType() === 'customer_order' || $action->getRecordType() === 'order_item') {
            if (!$record instanceof CustomerOrder && !$record instanceof OrderItem) {
                throw new \DomainException('Order not found.');
            }
            $this->orderStatusManager->finalizePendingCancellation($record);
        } elseif ($record instanceof ServiceBooking || $record instanceof PromoClaim) {
            if ($record->getStatus() !== 'Cancellation Pending') {
                throw new \DomainException('This transaction is no longer awaiting cancellation.');
            }
            $record->setStatus('Cancelled');
        } else {
            throw new \DomainException('Transaction not found.');
        }

        $action->markFinalized();
    }

    private function findRecord(CancellationUndo $action): CustomerOrder|OrderItem|ServiceBooking|PromoClaim|null
    {
        $class = match ($action->getRecordType()) {
            'customer_order' => CustomerOrder::class,
            'order_item' => OrderItem::class,
            'service_booking' => ServiceBooking::class,
            'promo_claim' => PromoClaim::class,
            default => null,
        };

        return $class === null ? null : $this->entityManager->find($class, $action->getRecordId(), LockMode::PESSIMISTIC_WRITE);
    }

    private function transactional(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        $ownsTransaction = !$connection->isTransactionActive();
        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $result = $operation();
            $this->entityManager->flush();
            if ($ownsTransaction) {
                $connection->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $connection->isTransactionActive()) {
                $connection->rollBack();
                $this->entityManager->clear();
            }

            throw $exception;
        }
    }
}
