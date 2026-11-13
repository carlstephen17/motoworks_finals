<?php

namespace App\Tests\Controller;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
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
        $buyButton = $shopPage->filter('.js-buy-now')->first();
        $token = $buyButton->attr('data-csrf-token');
        self::assertIsString($token);
        $connection = $entityManager->getConnection();
        $originalStock = $product->getStock();
        $originalAddress = $customer->getAddress();
        $reference = null;
        $submissionKey = '4a3e4ea9-17da-46bb-bac9-3c33a2364720';
        $connection->beginTransaction();

        try {
            $buyRequestPayload = [
                '_token' => $token,
                'quantity' => '1',
                'address' => 'Temporary verification address',
                'payment_method' => 'GCash',
                'submission_key' => $submissionKey,
            ];
            $client->request('POST', '/checkout/buy-now/'.$product->getId(), $buyRequestPayload);

            $this->assertResponseIsSuccessful();
            self::assertNull($client->getResponse()->headers->get('Location'));
            self::assertStringContainsString('application/json', (string) $client->getResponse()->headers->get('Content-Type'));
            $payload = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($payload);
            self::assertTrue($payload['success']);
            self::assertSame('GCash', $payload['payment_method']);
            self::assertSame('Pending', $payload['payment_status']);
            self::assertStringStartsWith('MWP-', $payload['payment_reference']);
            self::assertSame($originalStock - 1, $payload['remaining_stock']);
            $reference = $payload['payment_reference'];
            $firstOrderId = $payload['order_id'];

            $order = $entityManager->getRepository(CustomerOrder::class)->findOneBy(['paymentReference' => $reference]);
            $items = $entityManager->getRepository(OrderItem::class)->findBy(['paymentReference' => $reference]);
            self::assertInstanceOf(CustomerOrder::class, $order);
            self::assertSame($customer->getId(), $order->getUser()->getId());
            self::assertSame('Pending', $order->getPaymentStatus());
            self::assertCount(1, $items);
            self::assertSame($customer->getId(), $items[0]->getUser()->getId());
            self::assertSame($product->getId(), $items[0]->getProduct()->getId());
            self::assertSame($originalStock - 1, $entityManager->getRepository(Product::class)->find($product->getId())->getStock());
            self::assertSame($submissionKey, $order->getSubmissionKey());

            $client->request('POST', '/checkout/buy-now/'.$product->getId(), $buyRequestPayload);
            $this->assertResponseIsSuccessful();
            $retryPayload = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertTrue($retryPayload['success']);
            self::assertTrue($retryPayload['duplicate']);
            self::assertSame($firstOrderId, $retryPayload['order_id']);
            self::assertSame($originalStock - 1, $entityManager->getRepository(Product::class)->find($product->getId())->getStock());
            self::assertCount(1, $entityManager->getRepository(OrderItem::class)->findBy(['customerOrder' => $order]));

            $staff = $users->find(6);
            self::assertNotNull($staff);
            $client->loginUser($staff);
            $staffOrdersPage = $client->request('GET', '/staff/orders');
            $verifyForm = $staffOrdersPage->filter(sprintf('form[action="/staff/orders/%d/payment/verify"]', $firstOrderId));
            self::assertCount(1, $verifyForm);
            $verifyToken = $verifyForm->filter('input[name="_token"]')->attr('value');
            self::assertIsString($verifyToken);
            $client->request('POST', '/staff/orders/'.$firstOrderId.'/payment/verify', ['_token' => $verifyToken]);
            $this->assertResponseRedirects('/staff/orders');
            $entityManager->clear();
            $order = $entityManager->find(CustomerOrder::class, $firstOrderId);
            self::assertInstanceOf(CustomerOrder::class, $order);
            self::assertSame('Paid', $order->getPaymentStatus());
            self::assertSame($staff->getId(), $order->getPaymentVerifiedBy()->getId());

            $client->loginUser($customer);
            $client->request('GET', '/order-history/'.$firstOrderId.'/payment-status');
            $this->assertResponseIsSuccessful();
            self::assertSame('Paid', json_decode((string) $client->getResponse()->getContent(), true)['payment_status']);

            $historyUrl = $container->get('router')->generate('order_history');
            $client->request('GET', $historyUrl);
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', $reference);

            $otherCustomer = null;
            foreach ($users->findBy(['role' => 'customer']) as $candidate) {
                if ($candidate->getId() !== $customer->getId()) {
                    $otherCustomer = $candidate;
                    break;
                }
            }
            self::assertInstanceOf(User::class, $otherCustomer);
            $client->loginUser($otherCustomer);
            $client->request('GET', '/order-history/'.$firstOrderId.'/payment-status');
            $this->assertResponseStatusCodeSame(404);
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }

        self::assertNull($entityManager->getRepository(CustomerOrder::class)->findOneBy(['paymentReference' => $reference]));
        self::assertSame($originalStock, $entityManager->getRepository(Product::class)->find($product->getId())->getStock());
        self::assertSame($originalAddress, $users->find(3)?->getAddress());
    }
}
