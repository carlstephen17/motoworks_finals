<?php

namespace App\Tests\Controller;

use App\Entity\OrderItem;
use App\Repository\OrderItemRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccountControllerTest extends WebTestCase
{
    public function testCustomerOrderHistoryRendersTheLegacyOrderDetailsAndEditForms(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $orders = static::getContainer()->get(OrderItemRepository::class)->findForUser($user);

        $client->loginUser($user);
        $client->request('GET', '/order-history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#view-order-modal');
        $this->assertSelectorExists('#edit-order-form');
        if ($orders !== []) {
            $this->assertSelectorExists('.js-view-order');
        }
    }

    public function testInvalidOrderEditDoesNotChangeTheOrderOrStock(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);
        $orders = static::getContainer()->get(OrderItemRepository::class)->findForUser($user);
        $client->loginUser($user);

        $pendingOrder = null;
        foreach ($orders as $order) {
            if ($order->getCustomerOrder()?->getStatus() === 'Pending') {
                $pendingOrder = $order;
                break;
            }
        }
        if (!$pendingOrder instanceof OrderItem) {
            self::markTestSkipped('No pending customer order is available for the non-mutating edit-form check.');
        }

        $orderId = $pendingOrder->getId();
        $oldQuantity = $pendingOrder->getQuantity();
        $oldTotal = $pendingOrder->getTotalAmount();
        $oldStock = $pendingOrder->getProduct()->getStock();
        $client->request('POST', '/order-history/'.$pendingOrder->getId().'/edit', [
            'order_edit' => [
                '_token' => 'invalid',
                'quantity' => $oldQuantity + 1,
                'address' => 'Test address that must not be saved',
            ],
        ]);

        $this->assertResponseRedirects('/order-history');
        $unchangedOrder = static::getContainer()->get(OrderItemRepository::class)->find($orderId);
        self::assertInstanceOf(OrderItem::class, $unchangedOrder);
        self::assertSame($oldQuantity, $unchangedOrder->getQuantity());
        self::assertSame($oldTotal, $unchangedOrder->getTotalAmount());
        self::assertSame($oldStock, $unchangedOrder->getProduct()->getStock());
    }
}
