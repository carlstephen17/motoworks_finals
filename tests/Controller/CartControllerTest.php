<?php

namespace App\Tests\Controller;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class CartControllerTest extends WebTestCase
{
    public function testCartAcceptsLegacySessionQuantityShape(): void
    {
        $client = static::createClient();
        $session = $client->getContainer()->get('session.factory')->createSession();
        $session->set('cart', [1 => ['quantity' => 2]]);
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId(), null, '/'));

        $client->request('GET', '/cart');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.cart-product-name', 'Motorcycle Helmet');
        $this->assertSelectorTextContains('.cart-subtotal', '3,000.00');
    }

    public function testCartItemsCheckoutFormAndReceiptRenderFromSession(): void
    {
        $client = static::createClient();
        $session = $client->getContainer()->get('session.factory')->createSession();
        $session->set('cart', [1 => 2]);
        $session->set('checkout_confirmation', [
            'customer_name' => 'Test Customer',
            'phone' => '09123456789',
            'address' => 'Test address',
            'payment_method' => 'Cash on Delivery',
            'reference' => 'MWO-TEST-REFERENCE',
            'items' => [['name' => 'Motorcycle Helmet', 'quantity' => 2]],
        ]);
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId(), null, '/'));

        $client->request('GET', '/cart');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.cart-product-name', 'Motorcycle Helmet');
        $this->assertSelectorExists('#checkout-form');
        $this->assertSelectorTextContains('#payment-receipt-modal', 'MWO-TEST-REFERENCE');
    }

    public function testBuyNowPersistsAnOnlineOrderWithoutRedirectingToCheckout(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $users = $container->get(UserRepository::class);
        $customer = $users->find(3);
        $product = $container->get(ProductRepository::class)->find(1);
        self::assertNotNull($customer);
        self::assertNotNull($product);
        self::assertSame('customer', $customer->getRole());
        self::assertGreaterThan(0, $product->getStock());

        $client->loginUser($customer);
        $shopPage = $client->request('GET', '/shop');
        $buyButtonAction = (string) $shopPage->filter('button[onclick^="openBuyModal"]')->first()->attr('onclick');
        self::assertSame(1, preg_match('/,\s*("[^"]+")\s*\)$/', $buyButtonAction, $tokenMatch));
        $token = json_decode($tokenMatch[1], true);
        self::assertIsString($token);
        $connection = $entityManager->getConnection();
        $originalStock = $product->getStock();
        $originalAddress = $customer->getAddress();
        $reference = null;
        $connection->beginTransaction();

        try {
            $client->request('POST', '/checkout/buy-now/'.$product->getId(), [
                '_token' => $token,
                'quantity' => '1',
                'address' => 'Temporary verification address',
                'payment_method' => 'GCash',
            ]);

            $this->assertResponseIsSuccessful();
            self::assertNull($client->getResponse()->headers->get('Location'));
            self::assertStringContainsString('application/json', (string) $client->getResponse()->headers->get('Content-Type'));
            $payload = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($payload);
            self::assertTrue($payload['success']);
            self::assertSame('GCash', $payload['payment_method']);
            self::assertSame('Unverified', $payload['payment_status']);
            self::assertStringStartsWith('MWP-', $payload['payment_reference']);
            $reference = $payload['payment_reference'];

            $order = $entityManager->getRepository(CustomerOrder::class)->findOneBy(['paymentReference' => $reference]);
            $items = $entityManager->getRepository(OrderItem::class)->findBy(['paymentReference' => $reference]);
            self::assertInstanceOf(CustomerOrder::class, $order);
            self::assertSame('Unverified', $order->getPaymentStatus());
            self::assertCount(1, $items);
            self::assertSame($product->getId(), $items[0]->getProduct()->getId());
            self::assertSame($originalStock - 1, $entityManager->getRepository(Product::class)->find($product->getId())->getStock());

            $historyUrl = $container->get('router')->generate('order_history');
            $client->request('GET', $historyUrl);
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', $reference);
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }

        self::assertNull($entityManager->getRepository(CustomerOrder::class)->findOneBy(['paymentReference' => $reference]));
        self::assertSame($originalStock, $entityManager->getRepository(Product::class)->find($product->getId())->getStock());
        self::assertSame($originalAddress, $users->find(3)?->getAddress());
    }
}
