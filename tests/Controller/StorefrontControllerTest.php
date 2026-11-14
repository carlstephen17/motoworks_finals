<?php

namespace App\Tests\Controller;

use App\Controller\InformationController;
use App\Entity\Testimonial;
use App\Repository\UserRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StorefrontControllerTest extends WebTestCase
{
    public function testContactSubjectRemainsOptional(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();
        $subject = $crawler->filter('input[name="form[subject]"]');
        $this->assertCount(1, $subject);
        $this->assertNull($subject->attr('required'));
        $this->assertCount(1, $crawler->filter('form.contact-form button[type="submit"]'));
    }

    public function testContactSubmissionAcceptsBlankSubjectAndApprovesTestimonial(): void
    {
        static::createClient();
        $container = static::getContainer();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persistedTestimonial = null;
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(static function ($entity) use (&$persistedTestimonial): bool {
                if (!$entity instanceof Testimonial) {
                    return false;
                }

                $persistedTestimonial = $entity;

                return true;
            }));
        $entityManager->expects($this->once())->method('flush');

        $request = Request::create('/contact', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $token = $container->get('security.csrf.token_manager')->getToken('form')->getValue();
            $request->request->set('form', [
                'name' => 'Test Visitor',
                'email' => 'visitor@example.test',
                'subject' => '',
                'message' => 'A sample contact message.',
                'imageFile' => '',
                '_token' => $token,
            ]);

            $controller = new InformationController();
            $controller->setContainer($container);
            $response = $controller->contact(
                $request,
                $entityManager,
                $this->createStub(ImageUploader::class),
            );
        } finally {
            $requestStack->pop();
        }

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/contact', $response->headers->get('Location'));
        $this->assertInstanceOf(Testimonial::class, $persistedTestimonial);
        $this->assertSame('approved', $persistedTestimonial->getStatus());
        $this->assertSame('', $persistedTestimonial->getSubject());
        $this->assertSame('A sample contact message.', $persistedTestimonial->getMessage());
    }

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

    public function testPromoPageIncludesAuthenticationFormsForGuestClaims(): void
    {
        $client = static::createClient();
        $client->request('GET', '/promos');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#login-modal form#ajax-login-form');
        $this->assertSelectorExists('#claimForm');
    }

    public function testCustomerAuthenticationPersistsAcrossShopServicesAndPromos(): void
    {
        $client = static::createClient();
        $customer = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($customer);
        self::assertSame('customer', $customer->getRole());
        $client->loginUser($customer);

        foreach ([
            '/shop' => '#ajax-buy-form',
            '/services' => '#ajax-service-booking-form',
            '/promos' => '#claimForm',
        ] as $uri => $expectedForm) {
            $client->request('GET', $uri);

            $this->assertResponseIsSuccessful($uri);
            $this->assertSelectorExists($expectedForm);
            self::assertStringContainsString('window.isLoggedIn = true;', (string) $client->getResponse()->getContent());
        }
    }

    public function testLoginReturnsToTheIntendedStorefrontAndKeepsAuthenticationAcrossModules(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $customer = $container->get(UserRepository::class)->find(3);
            self::assertNotNull($customer);
            $customer->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($customer, 'TemporaryTestPassword!'));
            $entityManager->flush();

            $loginPage = $client->request('GET', '/');
            $csrfToken = $loginPage->filter('form.auth-form input[name="_csrf_token"]')->attr('value');
            self::assertNotEmpty($csrfToken);

            $client->request('POST', '/login', [
                '_csrf_token' => $csrfToken,
                '_target_path' => '/shop',
                'username' => $customer->getUserIdentifier(),
                'password' => 'TemporaryTestPassword!',
            ]);

            $this->assertResponseRedirects('/shop');
            $client->followRedirect();
            $this->assertResponseIsSuccessful();
            self::assertStringContainsString('window.isLoggedIn = true;', (string) $client->getResponse()->getContent());

            foreach (['/services', '/promos'] as $uri) {
                $client->request('GET', $uri);

                $this->assertResponseIsSuccessful($uri);
                self::assertStringContainsString('window.isLoggedIn = true;', (string) $client->getResponse()->getContent());
            }
        } finally {
            $entityManager->clear();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}
