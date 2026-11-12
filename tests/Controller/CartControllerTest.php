<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class CartControllerTest extends WebTestCase
{
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
}
