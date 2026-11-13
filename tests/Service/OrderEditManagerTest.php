<?php

namespace App\Tests\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Service\OrderEditManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class OrderEditManagerTest extends TestCase
{
    public function testUpdatingQuantityAndAddressAdjustsStockAndTotal(): void
    {
        [$user, $orderGroup, $order, $product] = $this->createOrder(stock: 8, quantity: 2);
        $manager = $this->createManager($product);

        $manager->updatePendingOrder($order, 4, 'New delivery address');

        self::assertSame(6, $product->getStock());
        self::assertSame(4, $order->getQuantity());
        self::assertSame('40.00', $order->getTotalAmount());
        self::assertSame('New delivery address', $orderGroup->getDeliveryAddress());
        self::assertSame('New delivery address', $user->getAddress());
    }

    public function testIncreasingQuantityBeyondAvailableStockDoesNotChangeTheOrder(): void
    {
        [, , $order, $product] = $this->createOrder(stock: 1, quantity: 2);
        $manager = $this->createManager($product);

        try {
            $manager->updatePendingOrder($order, 4, 'New delivery address');
            self::fail('Expected an insufficient-stock exception.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('Only 1 items are left in stock.', $exception->getMessage());
        }

        self::assertSame(1, $product->getStock());
        self::assertSame(2, $order->getQuantity());
        self::assertSame('20.00', $order->getTotalAmount());
    }

    public function testReducingQuantityRestoresStock(): void
    {
        [, , $order, $product] = $this->createOrder(stock: 8, quantity: 2);
        $manager = $this->createManager($product);

        $manager->updatePendingOrder($order, 1, 'New delivery address');

        self::assertSame(9, $product->getStock());
        self::assertSame('10.00', $order->getTotalAmount());
    }

    private function createManager(Product $product): OrderEditManager
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($product);

        return new OrderEditManager($entityManager);
    }

    /**
     * @return array{User, CustomerOrder, OrderItem, Product}
     */
    private function createOrder(int $stock, int $quantity): array
    {
        $user = (new User())->setAddress('Old address');
        $orderGroup = new CustomerOrder($user, 'Old address', 'Cash on Delivery', 'MWO-TEST-ORDER');
        $product = (new Product())->setName('Test product')->setPrice('10.00')->setStock($stock);
        $idProperty = new \ReflectionProperty(Product::class, 'id');
        $idProperty->setValue($product, 1);
        $order = new OrderItem($user, $product, $quantity, number_format($quantity * 10, 2, '.', ''), 'Cash on Delivery', 'MWO-TEST-ORDER', $orderGroup, '10.00');

        return [$user, $orderGroup, $order, $product];
    }
}
