<?php

namespace App\Tests\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Service\OrderStatusManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class OrderStatusManagerTest extends TestCase
{
    public function testCancellingAndReactivatingAGroupedOrderUpdatesEveryLineAndStock(): void
    {
        [$orderGroup, $items, $products] = $this->createGroupedOrder();
        $manager = $this->createManager($items, $products);

        $manager->updateStatus($items[0], 'Cancelled', 'Customer requested cancellation');

        self::assertSame('Cancelled', $orderGroup->getStatus());
        self::assertSame('Customer requested cancellation', $orderGroup->getCancellationReason());
        self::assertSame(['Cancelled', 'Cancelled'], array_map(
            static fn (OrderItem $item): ?string => $item->getStatus(),
            $items,
        ));
        self::assertSame([12, 8], array_values(array_map(
            static fn (Product $product): int => $product->getStock(),
            $products,
        )));

        $manager->updateStatus($items[1], 'Processing');

        self::assertSame('Processing', $orderGroup->getStatus());
        self::assertNull($orderGroup->getCancellationReason());
        self::assertSame(['Processing', 'Processing'], array_map(
            static fn (OrderItem $item): ?string => $item->getStatus(),
            $items,
        ));
        self::assertSame([10, 7], array_values(array_map(
            static fn (Product $product): int => $product->getStock(),
            $products,
        )));
    }

    public function testCancellationRequiresAReason(): void
    {
        [, $items, $products] = $this->createGroupedOrder();
        $manager = $this->createManager($items, $products);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Provide a cancellation reason');

        $manager->updateStatus($items[0], 'Cancelled');
    }

    public function testCustomerOrderGroupCanBeUpdatedWithoutLineItems(): void
    {
        $orderGroup = new CustomerOrder(new User(), 'Bayawan City', 'Cash on Delivery', 'MWO-EMPTY-GROUP');
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $orderRepository = $this->createStub(OrderItemRepository::class);
        $orderRepository->method('findItemsInGroup')->willReturn([]);
        $manager = new OrderStatusManager($entityManager, $orderRepository);

        $manager->updateStatus($orderGroup, 'Cancelled', 'Staff cancellation');

        self::assertSame('Cancelled', $orderGroup->getStatus());
        self::assertSame('Staff cancellation', $orderGroup->getCancellationReason());
    }

    /**
     * @param list<OrderItem> $items
     * @param array<int, Product> $products
     */
    private function createManager(array $items, array $products): OrderStatusManager
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturnCallback(
            static fn (string $class, int $id): ?Product => $products[$id] ?? null,
        );

        $orderRepository = $this->createStub(OrderItemRepository::class);
        $orderRepository->method('findItemsInGroup')->willReturn($items);

        return new OrderStatusManager($entityManager, $orderRepository);
    }

    /**
     * @return array{CustomerOrder, list<OrderItem>, array<int, Product>}
     */
    private function createGroupedOrder(): array
    {
        $user = new User();
        $orderGroup = new CustomerOrder($user, '1 Main Street', 'Cash on Delivery', 'MWO-TEST-ORDER');
        $firstProduct = $this->createProduct(1, 10);
        $secondProduct = $this->createProduct(2, 7);
        $items = [
            new OrderItem($user, $firstProduct, 2, '20.00', 'Cash on Delivery', 'MWO-TEST-ORDER', $orderGroup, '10.00'),
            new OrderItem($user, $secondProduct, 1, '5.00', 'Cash on Delivery', 'MWO-TEST-ORDER', $orderGroup, '5.00'),
        ];

        return [$orderGroup, $items, [1 => $firstProduct, 2 => $secondProduct]];
    }

    private function createProduct(int $id, int $stock): Product
    {
        $product = (new Product())->setName('Test product')->setStock($stock);
        $idProperty = new \ReflectionProperty(Product::class, 'id');
        $idProperty->setValue($product, $id);

        return $product;
    }
}
