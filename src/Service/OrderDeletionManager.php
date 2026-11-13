<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\OrderItemRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class OrderDeletionManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemRepository $orders,
    ) {
    }

    public function delete(CustomerOrder $order): void
    {
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
            if ($lockedOrder->getStatus() === 'Cancellation Pending') {
                throw new \DomainException('This order is awaiting cancellation confirmation.');
            }

            $items = $this->orders->findItemsInGroup($lockedOrder);
            foreach ($items as $item) {
                $this->entityManager->lock($item, LockMode::PESSIMISTIC_WRITE);
            }

            if ($lockedOrder->getStatus() === 'Pending') {
                foreach ($this->lockInventory($items) as $entry) {
                    $entry['product']->setStock($entry['product']->getStock() + $entry['quantity']);
                }
            }

            foreach ($items as $item) {
                $this->entityManager->remove($item);
            }
            $this->entityManager->remove($lockedOrder);
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
     * @return list<array{product: Product, quantity: int}>
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
            $inventory[] = ['product' => $product, 'quantity' => $quantity];
        }

        return $inventory;
    }
}
