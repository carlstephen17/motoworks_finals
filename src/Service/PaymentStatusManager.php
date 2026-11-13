<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class PaymentStatusManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function verify(CustomerOrder $order, User $staff): void
    {
        if (!in_array($staff->getRole(), ['admin', 'staff'], true)) {
            throw new \DomainException('Only authorized staff can confirm payment.');
        }

        $connection = $this->entityManager->getConnection();
        $ownsTransaction = !$connection->isTransactionActive();
        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $lockedOrder = $this->entityManager->find(CustomerOrder::class, $order->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$lockedOrder instanceof CustomerOrder) {
                throw new \DomainException('Order not found.');
            }
            if ($lockedOrder->getStatus() === 'Cancelled') {
                throw new \DomainException('Cancelled orders cannot be marked as paid.');
            }

            $lockedOrder->markPaymentVerified($staff);
            $this->entityManager->flush();
            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $connection->isTransactionActive()) {
                $connection->rollBack();
                $this->entityManager->clear();
            }

            throw $exception;
        }
    }
}
