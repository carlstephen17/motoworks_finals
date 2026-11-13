<?php

namespace App\Tests\Controller;

use App\Entity\OrderItem;
use App\Entity\CustomerOrder;
use App\Repository\ProductRepository;
use App\Repository\OrderItemRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccountControllerTest extends WebTestCase
{
    public function testCustomerOrderHistoryIsViewAndCancelOnly(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $orders = static::getContainer()->get(OrderItemRepository::class)->findForUser($user);

        $client->loginUser($user);
        $client->request('GET', '/order-history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#view-order-modal');
        $this->assertSelectorNotExists('#edit-order-form');
        $this->assertSelectorNotExists('.js-edit-order');
        if ($orders !== []) {
            $this->assertSelectorExists('.js-view-order');
        }
    }

    public function testCustomerOrderEditEndpointRejectsModification(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('POST', '/order-history/1/edit', [
            'order_edit' => [
                '_token' => 'invalid',
                'quantity' => 999,
                'address' => 'Test address that must not be saved',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCustomerCanUndoOrderCancellationWithoutRestoringStockEarly(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $customer = $container->get(UserRepository::class)->find(3);
        $product = null;
        foreach ($container->get(ProductRepository::class)->findActiveProducts() as $candidate) {
            if ($candidate->getStock() > 0) {
                $product = $candidate;
                break;
            }
        }
        self::assertNotNull($customer);
        if ($product === null) {
            self::markTestSkipped('No in-stock product is available for the cancellation flow test.');
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $orderId = null;

        try {
            $unitPrice = $product->getPrice();
            $product->setStock($product->getStock() - 1);
            $order = new CustomerOrder($customer, 'Cancellation test address', 'Cash on Delivery', 'MWO-CANCEL-TEST');
            $item = new OrderItem($customer, $product, 1, $unitPrice, 'Cash on Delivery', 'MWO-CANCEL-TEST', $order, $unitPrice);
            $entityManager->persist($order);
            $entityManager->persist($item);
            $entityManager->flush();
            $orderId = $item->getId();
            self::assertNotNull($orderId);

            $client->loginUser($customer);
            $history = $client->request('GET', '/order-history');
            $form = $history->filter(sprintf('form[action="/order-history/%d/cancel"]', $orderId))->form();
            $stockAfterPlacement = $product->getStock();
            $client->xmlHttpRequest('POST', '/order-history/'.$orderId.'/cancel', [
                '_token' => $form->get('_token')->getValue(),
            ]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($result);
            self::assertTrue($result['success']);
            self::assertSame('customer_order', $result['action']['record_type']);
            self::assertSame('Cancellation Pending', $connection->fetchOne(
                'SELECT orderStatus FROM customer_order WHERE orderGroupId = ?',
                [$order->getId()],
            ));
            self::assertSame('Cancellation Pending', $connection->fetchOne(
                'SELECT orderStatus FROM `order` WHERE orderId = ?',
                [$orderId],
            ));
            self::assertSame($stockAfterPlacement, (int) $connection->fetchOne(
                'SELECT productStock FROM product WHERE productId = ?',
                [$product->getId()],
            ));

            $action = $result['action'];
            $client->xmlHttpRequest('POST', $action['undo_url'], ['_token' => $action['undo_token']]);

            $this->assertResponseIsSuccessful();
            self::assertSame('Pending', $connection->fetchOne(
                'SELECT orderStatus FROM customer_order WHERE orderGroupId = ?',
                [$order->getId()],
            ));
            self::assertSame($stockAfterPlacement, (int) $connection->fetchOne(
                'SELECT productStock FROM product WHERE productId = ?',
                [$product->getId()],
            ));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }

        self::assertNotNull($orderId);
        self::assertNull($entityManager->getRepository(OrderItem::class)->find($orderId));
    }
}
