<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class OrderEditManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function updatePendingOrder(OrderItem $order, int $quantity, string $address): void
    {
        $address = trim($address);
        if ($quantity < 1 || $address === '' || mb_strlen($address) > 65535) {
            throw new \DomainException('Enter a valid quantity and delivery address.');
        }

        $orderGroup = $order->getCustomerOrder();
        if (!$orderGroup instanceof CustomerOrder) {
            throw new \DomainException('This order can no longer be edited.');
        }

        $connection = $this->entityManager->getConnection();
        $ownsTransaction = !$connection->isTransactionActive();
        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $this->entityManager->refresh($orderGroup, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($order, LockMode::PESSIMISTIC_WRITE);
            if ($orderGroup->getStatus() !== 'Pending') {
                throw new \DomainException('Only pending orders can be edited.');
            }

            $productId = $order->getProduct()->getId();
            $product = $productId === null
                ? null
                : $this->entityManager->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
            if (!$product instanceof Product) {
                throw new \DomainException('The ordered product could not be found.');
            }

            $quantityDifference = $quantity - $order->getQuantity();
            if ($quantityDifference > $product->getStock()) {
                throw new \DomainException(sprintf(
                    'Cannot increase quantity by %d. Only %d items are left in stock.',
                    $quantityDifference,
                    $product->getStock(),
                ));
            }

            $unitPrice = $order->getUnitPrice() ?? $product->getPrice();
            $product->setStock($product->getStock() - $quantityDifference);
            $order->setQuantity($quantity);
            $order->setUnitPrice($unitPrice);
            $order->setTotalAmount(number_format((float) $unitPrice * $quantity, 2, '.', ''));
            $orderGroup->setDeliveryAddress($address);
            $orderGroup->getUser()->setAddress($address);

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
