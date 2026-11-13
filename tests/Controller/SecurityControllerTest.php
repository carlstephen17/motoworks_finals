<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIncludesCsrfAndRememberMeFields(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#login-modal');
        $this->assertSelectorExists('#ajax-login-form');
        $this->assertSelectorCount(2, '#register-modal .toggle-password');
        $this->assertSelectorExists('input[name="_csrf_token"]');
        $this->assertSelectorExists('input[name="_remember_me"]');
    }

    public function testInvalidLoginReturnsToLoginWithoutAuthenticating(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('#ajax-login-form')->form();
        $form['username'] = 'no-such-user-'.bin2hex(random_bytes(6));
        $form['password'] = 'invalid-password';

        $client->submit($form);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-error');
    }

    public function testSuccessfulLoginShowsTimedWelcomeNotification(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $users = $container->get(UserRepository::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $username = 'phase1-login-'.bin2hex(random_bytes(5));
        $password = 'SecureTestPassword123';
        $user = (new User())
            ->setUsername($username)
            ->setFirstName('Phase')
            ->setLastName('Customer')
            ->setEmail($username.'@example.test')
            ->setRole('customer');
        $user->setPassword($hasher->hashPassword($user, $password));

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $entityManager->persist($user);
            $entityManager->flush();
            $loginPage = $client->request('GET', '/login');
            $form = $loginPage->filter('#ajax-login-form')->form();
            $form['username'] = $username;
            $form['password'] = $password;

            $client->submit($form);

            $this->assertResponseRedirects();
            $client->followRedirect();
            if ($client->getResponse()->isRedirect()) {
                $client->followRedirect();
            }

            $this->assertSelectorTextContains('#auth-success-notification', 'Login successful! Welcome back.');
            $this->assertSelectorExists('#auth-success-notification[data-auto-dismiss]');
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminLoginRedirectsToAdminDashboardAndLogoutInvalidatesSession(): void
    {
        $this->assertLoginAndLogout('admin', '/admin', '/admin');
    }

    public function testStaffLoginRedirectsToStaffDashboardAndLogoutInvalidatesSession(): void
    {
        $this->assertLoginAndLogout('staff', '/staff', '/staff');
    }

    public function testCustomerLoginAndLogoutPreserveCustomerAccessRules(): void
    {
        $this->assertLoginAndLogout('customer', '/dashboard', '/profile');
    }

    public function testRegistrationCreatesCustomerWithHashedPassword(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $users = $container->get(UserRepository::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $username = 'phase1-register-'.bin2hex(random_bytes(5));
        $password = 'SecureTestPassword123';
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $registrationPage = $client->request('GET', '/register');
            $form = $registrationPage->selectButton('Register Account')->form();
            $form['registration[username]'] = $username;
            $form['registration[firstName]'] = 'New';
            $form['registration[lastName]'] = 'Customer';
            $form['registration[email]'] = strtoupper($username).'@example.test';
            $form['registration[phone]'] = '09123456789';
            $form['registration[plainPassword][first]'] = $password;
            $form['registration[plainPassword][second]'] = $password;

            $client->submit($form);

            $this->assertResponseRedirects();
            $user = $users->findOneBy(['username' => $username]);
            self::assertInstanceOf(User::class, $user);
            self::assertSame('customer', $user->getRole());
            self::assertNotSame($password, $user->getPassword());
            self::assertTrue($hasher->isPasswordValid($user, $password));
            self::assertSame(strtolower($username.'@example.test'), $user->getEmail());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testRegistrationRejectsDuplicateUsername(): void
    {
        $client = static::createClient();
        $existing = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($existing);
        $registrationPage = $client->request('GET', '/register');
        $form = $registrationPage->selectButton('Register Account')->form();
        $form['registration[username]'] = strtoupper($existing->getUsername());
        $form['registration[firstName]'] = 'Duplicate';
        $form['registration[lastName]'] = 'Customer';
        $form['registration[email]'] = 'duplicate-'.bin2hex(random_bytes(5)).'@example.test';
        $form['registration[phone]'] = '09123456789';
        $form['registration[plainPassword][first]'] = 'SecureTestPassword123';
        $form['registration[plainPassword][second]'] = 'SecureTestPassword123';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('#register-page-form', 'That username or email address is already registered.');
    }

    public function testForgotPasswordForUnknownAddressUsesGenericMessage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/forgot-password');
        $form = $crawler->selectButton('Send reset link')->form();
        $form['form[email]'] = 'no-such-user-'.bin2hex(random_bytes(6)).'@example.invalid';

        $client->submit($form);

        $this->assertResponseRedirects('/forgot-password');
        $client->followRedirect();
        $this->assertSelectorTextContains('[role="status"]', 'If that email exists, reset instructions have been sent.');
    }

    public function testResetPageRejectsMissingToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password?token=invalid');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'This password reset link is invalid or has expired.');
        $this->assertSelectorNotExists('#reset-password-form');
    }

    private function assertLoginAndLogout(string $role, string $loginRedirect, string $protectedPath): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $username = 'security-'.$role.'-'.bin2hex(random_bytes(5));
        $password = 'SecureTestPassword123';
        $user = (new User())
            ->setUsername($username)
            ->setFirstName('Security')
            ->setLastName(ucfirst($role))
            ->setEmail($username.'@example.test')
            ->setRole($role);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->persist($user);
            $entityManager->flush();

            $loginPage = $client->request('GET', '/login');
            $form = $loginPage->filter('#ajax-login-form')->form();
            $form['username'] = $username;
            $form['password'] = $password;
            $form['_target_path'] = match ($role) {
                'admin' => '/shop',
                'staff' => '/admin/users',
                default => '/dashboard',
            };
            $client->submit($form);

            $this->assertResponseRedirects($loginRedirect);
            for ($redirects = 0; $redirects < 3 && $client->getResponse()->isRedirect(); ++$redirects) {
                $client->followRedirect();
            }
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('#auth-success-notification', 'Login successful! Welcome back.');
            self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control', ''));

            $client->request('GET', $protectedPath);
            $this->assertResponseIsSuccessful();
            self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control', ''));

            $client->request('GET', '/logout');
            $this->assertResponseStatusCodeSame(405);

            $logoutPage = $client->request('GET', $protectedPath);
            $this->assertResponseIsSuccessful();
            $logoutForm = $logoutPage->filter('form[action="/logout"]')->form();
            $client->submit($logoutForm);
            $this->assertResponseRedirects('/login');
            $this->assertResponseHeaderSame('Clear-Site-Data', '"cache", "cookies"');
            self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control', ''));

            $client->request('GET', $protectedPath);
            $this->assertResponseRedirects('/login');
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
