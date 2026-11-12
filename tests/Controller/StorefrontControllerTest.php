<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StorefrontControllerTest extends WebTestCase
{
    public function testStorefrontAndInformationPagesRenderSuccessfully(): void
    {
        $client = static::createClient();

        $routes = [
            '/' => 'Bencalo MotoWorks',
            '/shop' => 'All Products & Genuine Parts',
            '/services' => 'Professional Services & Tune-Ups',
            '/promos' => 'Active Moto Promos & Bundles',
            '/contact' => 'Get in Touch With Us',
            '/about' => 'Engineered for Performance',
            '/team' => 'Meet Our Expert Team',
            '/battery' => '5 Signs Your Battery is Failing',
            '/oil' => 'Importance of Regular Oil Changes',
            '/cooling' => 'Cooling System Maintenance',
            '/tires' => 'Proper Tire Inflation Guide',
            '/faq' => 'Frequently Asked Questions',
            '/privacy' => 'Privacy Policy',
            '/terms' => 'Terms & Conditions',
            '/terms-of-service' => 'Terms of Service',
        ];

        foreach ($routes as $uri => $expectedHeading) {
            $crawler = $client->request('GET', $uri);
            $this->assertResponseIsSuccessful(sprintf('Failed asserting that "%s" responded successfully.', $uri));
            $this->assertAnySelectorTextContains('h1, h2, title', $expectedHeading);
        }
    }
}
