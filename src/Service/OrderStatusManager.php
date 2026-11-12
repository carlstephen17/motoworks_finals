<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\OrderItemRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class OrderStatusManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemRepository $orders,
    ) {
    }

    public function updateStatus(OrderItem $selectedItem, string $nextStatus, string $cancellationReason = ''): void
    {
        if (!in_array($nextStatus, ['Pending', 'Processing', 'Shipped', 'Completed', 'Cancelled'], true)) {
            throw new \DomainException('Invalid order status.');
        }

        $connection = $this->entityManager->getConnection();
        $ownsTransaction = !$connection->isTransactionActive();
        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $orderGroup = $selectedItem->getCustomerOrder();
            if ($orderGroup instanceof CustomerOrder) {
                $this->entityManager->lock($orderGroup, LockMode::PESSIMISTIC_WRITE);
            } else {
                $this->entityManager->lock($selectedItem, LockMode::PESSIMISTIC_WRITE);
            }

            $items = $this->orders->findItemsInGroup($selectedItem);
            $currentStatus = $orderGroup?->getStatus() ?? $selectedItem->getStatus() ?? 'Pending';
            if ($currentStatus === 'Completed') {
                throw new \DomainException('Completed orders cannot be updated.');
            }

            if ($currentStatus === 'Shipped' && $nextStatus === 'Cancelled') {
                throw new \DomainException('Shipped orders cannot be cancelled.');
            }

            if ($nextStatus === 'Cancelled' && $currentStatus !== 'Cancelled') {
                $cancellationReason = trim($cancellationReason);
                if ($cancellationReason === '' || mb_strlen($cancellationReason) > 500) {
                    throw new \DomainException('Provide a cancellation reason of no more than 500 characters.');
                }
            } elseif ($nextStatus === 'Cancelled' && $cancellationReason === '') {
                $cancellationReason = $orderGroup?->getCancellationReason() ?? '';
            }

            $inventory = [];
            if ($nextStatus === 'Cancelled' && $currentStatus !== 'Cancelled') {
                $inventory = $this->lockInventory($items);
            } elseif ($currentStatus === 'Cancelled' && $nextStatus !== 'Cancelled') {
                if (!in_array($nextStatus, ['Pending', 'Processing'], true)) {
                    throw new \DomainException('Cancelled orders must be reactivated as Pending or Processing.');
                }
                $inventory = $this->lockInventory($items);
                foreach ($inventory as $productId => $entry) {
                    if ($entry['product']->getStock() < $entry['quantity']) {
                        throw new \DomainException('Insufficient stock to reactivate this cancelled order.');
                    }
                }
            }

            if ($nextStatus === 'Cancelled' && $currentStatus !== 'Cancelled') {
                foreach ($inventory as $entry) {
                    $entry['product']->setStock($entry['product']->getStock() + $entry['quantity']);
                }
            } elseif ($currentStatus === 'Cancelled' && $nextStatus !== 'Cancelled') {
                foreach ($inventory as $entry) {
                    $entry['product']->setStock($entry['product']->getStock() - $entry['quantity']);
                }
            }

            foreach ($items as $item) {
                $item->setStatus($nextStatus);
            }
            if ($orderGroup instanceof CustomerOrder) {
                $orderGroup->setStatus($nextStatus);
                $orderGroup->setCancellationReason($nextStatus === 'Cancelled' ? $cancellationReason : null);
            }

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

    /**
     * @param list<OrderItem> $items
     * @return array<int, array{product: Product, quantity: int}>
     */
    private function lockInventory(array $items): array
    {
        $quantities = [];
        foreach ($items as $item) {
            $productId = $item->getProduct()->getId();
            if ($productId === null) {
                throw new \DomainException('An ordered product could not be found.');
            }
            $quantities[$productId] = ($quantities[$productId] ?? 0) + $item->getQuantity();
        }

        $inventory = [];
        foreach ($quantities as $productId => $quantity) {
            $product = $this->entityManager->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
            if (!$product instanceof Product) {
                throw new \DomainException('An ordered product could not be found.');
            }
            $inventory[$productId] = ['product' => $product, 'quantity' => $quantity];
        }

        return $inventory;
    }
}
