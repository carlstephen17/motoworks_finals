<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginRouteRedirectsToHomepageWithAuthModals(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseRedirects('/?auth=login');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#login-modal');
        $this->assertSelectorExists('#ajax-login-form');
        $this->assertSelectorExists('#register-modal #ajax-register-form');
        $this->assertSelectorExists('button.btn-login-header[aria-controls="login-modal"]');
        $this->assertSelectorExists('button.btn-signup-header[aria-controls="register-modal"]');
        $this->assertSelectorCount(2, '#register-modal .toggle-password');
        $this->assertSelectorExists('input[name="_csrf_token"]');
        $this->assertSelectorExists('input[name="_remember_me"]');
        self::assertStringNotContainsString('Continue in the sign-in dialog.', (string) $client->getResponse()->getContent());
    }

    public function testRegisterPageRedirectsToHomepageWithRegistrationModalOpen(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseRedirects('/?auth=register');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#register-modal #ajax-register-form');
        self::assertStringNotContainsString('Create Your Account', (string) $client->getResponse()->getContent());
    }

    public function testInvalidLoginReturnsToLoginWithoutAuthenticating(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('#ajax-login-form')->form();
        $form['username'] = 'no-such-user-'.bin2hex(random_bytes(6));
        $form['password'] = 'invalid-password';

        $client->submit($form);

        $this->assertResponseRedirects('/login');
        for ($redirects = 0; $redirects < 3 && $client->getResponse()->isRedirect(); ++$redirects) {
            $client->followRedirect();
        }
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#login-modal-alert', 'Wrong username or password.');
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
            $loginPage = $client->request('GET', '/');
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

    public function testLoggingOutDoesNotInvalidateAnotherUsersSession(): void
    {
        $kernel = static::bootKernel();
        $container = static::getContainer();
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);

        $adminClient = new KernelBrowser($kernel);
        $customerClient = new KernelBrowser($kernel);
        $adminClient->disableReboot();
        $customerClient->disableReboot();
        $adminClient->loginUser($admin);
        $customerClient->loginUser($customer);

        $adminPage = $adminClient->request('GET', '/admin');
        self::assertSame(200, $adminClient->getResponse()->getStatusCode());
        $logoutForm = $adminPage->filter('form[action="/logout"]')->form();
        $adminClient->submit($logoutForm);
        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame('/', parse_url((string) $adminClient->getResponse()->headers->get('Location'), PHP_URL_PATH));
        $logoutHomePage = $adminClient->followRedirect();
        self::assertGreaterThan(0, $logoutHomePage->filter('#login-modal')->count());
        self::assertGreaterThan(0, $logoutHomePage->filter('#register-modal')->count());
        self::assertStringNotContainsString('Continue in the sign-in dialog.', (string) $adminClient->getResponse()->getContent());

        $customerClient->request('GET', '/profile');
        self::assertSame(200, $customerClient->getResponse()->getStatusCode());
        $adminClient->request('GET', '/admin');
        self::assertTrue($adminClient->getResponse()->isRedirect());
        self::assertSame('/login', parse_url((string) $adminClient->getResponse()->headers->get('Location'), PHP_URL_PATH));
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
            $registrationPage = $client->request('GET', '/');
            $form = $registrationPage->filter('#ajax-register-form')->form();
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
        $registrationPage = $client->request('GET', '/');
        $form = $registrationPage->filter('#ajax-register-form')->form();
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

            $loginPage = $client->request('GET', '/');
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

            $homePage = $client->request('GET', '/');
            $this->assertResponseIsSuccessful();
            $homeLogoutForm = $homePage->filter('form[action="/logout"]');
            self::assertSame('false', $homeLogoutForm->attr('data-turbo'));
            $homeLogoutToken = $homeLogoutForm->filter('input[name="_csrf_token"]')->attr('value');
            self::assertNotEmpty($homeLogoutToken);
            self::assertNotSame('csrf-token', $homeLogoutToken);

            $client->request('GET', $protectedPath);
            $this->assertResponseIsSuccessful();
            self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control', ''));

            $client->request('GET', '/logout');
            $this->assertResponseStatusCodeSame(405);

            $client->request('POST', '/logout', ['_csrf_token' => 'invalid']);
            $this->assertResponseStatusCodeSame(403);
            $client->request('GET', $protectedPath);
            $this->assertResponseIsSuccessful();

            $logoutPage = $client->request('GET', $protectedPath);
            $this->assertResponseIsSuccessful();
            $logoutForm = $logoutPage->filter('form[action="/logout"]')->form();
            $logoutFormNode = $logoutPage->filter('form[action="/logout"]');
            self::assertSame('false', $logoutFormNode->attr('data-turbo'));
            $logoutToken = $logoutFormNode->filter('input[name="_csrf_token"]')->attr('value');
            self::assertNotEmpty($logoutToken);
            self::assertNotSame('csrf-token', $logoutToken);
            $client->submit($logoutForm);
            $this->assertResponseRedirects('/');
            $this->assertResponseHeaderSame('Clear-Site-Data', '"cache", "cookies"');
            self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control', ''));

            $client->request('GET', $protectedPath);
            $this->assertResponseRedirects('/login');

            $loginPage = $client->request('GET', '/login');
            $this->assertResponseRedirects('/?auth=login');
            $loginPage = $client->followRedirect();
            $this->assertResponseIsSuccessful();
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

            $client->request('GET', $protectedPath);
            $this->assertResponseIsSuccessful();
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
