<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class OrderCreationManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<int, int> $requestedItems Product IDs mapped to quantities
     */
    public function create(User $creator, User $customer, string $deliveryAddress, array $requestedItems): CustomerOrder
    {
        if ($customer->getRole() !== 'customer') {
            throw new \DomainException('Choose a valid customer.');
        }
        if (trim($deliveryAddress) === '' || mb_strlen($deliveryAddress) > 2000) {
            throw new \DomainException('Enter a valid delivery address.');
        }
        if ($requestedItems === [] || count($requestedItems) > 20) {
            throw new \DomainException('An order must contain between 1 and 20 items.');
        }

        $quantities = [];
        foreach ($requestedItems as $productId => $quantity) {
            $productId = filter_var($productId, FILTER_VALIDATE_INT);
            if ($productId === false || $productId < 1 || $quantity < 1 || $quantity > 999) {
                throw new \DomainException('One or more order items are invalid.');
            }
            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }
        if (array_filter($quantities, static fn (int $quantity): bool => $quantity > 999) !== []) {
            throw new \DomainException('The total quantity of a product cannot exceed 999.');
        }

        ksort($quantities, SORT_NUMERIC);
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $lockedProducts = [];
            foreach ($quantities as $productId => $quantity) {
                $product = $this->entityManager->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
                if (!$product instanceof Product || !$product->isActive()) {
                    throw new \DomainException('One or more selected products are unavailable.');
                }
                if ($quantity > $product->getStock()) {
                    throw new \DomainException($product->getName().' has only '.$product->getStock().' in stock.');
                }
                $lockedProducts[$productId] = $product;
            }

            $paymentMethod = 'Cash on Delivery';
            $order = new CustomerOrder($customer, trim($deliveryAddress), $paymentMethod, '');
            $order->setCreatedByStaff($creator);
            $this->entityManager->persist($order);
            $this->entityManager->flush();
            $paymentReference = 'MWO-'.(new \DateTimeImmutable())->format('Ymd').'-'.$order->getId();
            $order->setPaymentReference($paymentReference);

            foreach ($quantities as $productId => $quantity) {
                $product = $lockedProducts[$productId];
                $unitPrice = $product->getPrice();
                $product->setStock($product->getStock() - $quantity);
                $this->entityManager->persist(new OrderItem(
                    $customer,
                    $product,
                    $quantity,
                    number_format((float) $unitPrice * $quantity, 2, '.', ''),
                    $paymentMethod,
                    $paymentReference,
                    $order,
                    $unitPrice,
                ));
            }
            $this->entityManager->flush();
            $connection->commit();

            return $order;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
