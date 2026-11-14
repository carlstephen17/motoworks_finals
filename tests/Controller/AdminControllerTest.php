<?php

namespace App\Tests\Controller;

use App\Entity\Service;
use App\Entity\Product;
use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Promo;
use App\Entity\PromoClaim;
use App\Entity\ServiceBooking;
use App\Form\ServiceType;
use App\Repository\PromoRepository;
use App\Repository\PromoClaimRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\CustomerOrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    public function testAdminModulePagesAreReachableWithoutChangingRecords(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        foreach ([
            '/admin',
            '/admin/users',
            '/admin/products',
            '/admin/orders',
            '/admin/appointments',
            '/admin/services',
            '/admin/promos',
            '/admin/promo-history',
        ] as $path) {
            $client->request('GET', $path);
            $this->assertResponseIsSuccessful($path);
            $this->assertSelectorExists('main.admin-main > header.main-header');
        }
    }

    public function testAdminDashboardStatisticsAreDatabaseDriven(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/dashboard/stats');

        $this->assertResponseIsSuccessful();
        $counts = json_decode((string) $client->getResponse()->getContent(), true)['counts'];
        $users = static::getContainer()->get(UserRepository::class);
        $promos = static::getContainer()->get(PromoRepository::class);
        self::assertSame($users->count([]), $counts['users']);
        self::assertSame($users->countByRole('customer'), $counts['customers']);
        self::assertSame($users->countByRole('staff'), $counts['staff']);
        self::assertSame($promos->count([]), $counts['promos']);
        $productStatusCounts = static::getContainer()->get(\App\Repository\ProductRepository::class)->getStatusCounts();
        self::assertSame($productStatusCounts['active'], $counts['productsActive']);
        self::assertSame($productStatusCounts['inactive'], $counts['productsInactive']);
        self::assertArrayHasKey('totalRecords', $counts);
        self::assertArrayHasKey('paymentRecords', $counts);
    }

    public function testOrderAndAppointmentStatisticsMatchPersistedDatabaseRecords(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $orders = $container->get(\App\Repository\CustomerOrderRepository::class);
        $orderCounts = $orders->getStatusCounts();
        $crawler = $client->request('GET', '/admin/orders');
        $this->assertResponseIsSuccessful();
        foreach ($orderCounts as $key => $count) {
            self::assertSame(
                (string) $count,
                $crawler->filter('[data-order-count="'.$key.'"] h3')->text(),
                'The Orders statistic '.$key.' should match the database count.',
            );
        }

        $bookings = $container->get(ServiceBookingRepository::class);
        $bookingCounts = $bookings->getAdminStatusCounts();
        $crawler = $client->request('GET', '/admin/appointments');
        $this->assertResponseIsSuccessful();
        foreach ($bookingCounts as $key => $count) {
            self::assertSame(
                (string) $count,
                $crawler->filter('[data-appointment-count="'.$key.'"] h3')->text(),
                'The Appointments statistic '.$key.' should match the database count.',
            );
        }

        $dashboard = $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        self::assertSame((string) $orders->count([]), $dashboard->filter('[data-dashboard-count="orders"]')->text());
        self::assertSame((string) $bookings->count([]), $dashboard->filter('[data-dashboard-count="appointments"]')->text());
    }

    public function testAdminDashboardUsesLegacySectionsAndNavigation(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('aside.admin-sidebar');
        $this->assertSelectorExists('a[aria-current="page"][href="/admin"]');
        $this->assertSelectorExists('.admin-sidebar a[href="/"]');
        $this->assertSelectorTextContains('.admin-sidebar a[href="/"]', 'Website');
        $this->assertSelectorExists('link[href="/admin/css/admin_dashboard.css"]');
        $this->assertSelectorTextContains('.main-header h2', 'Dashboard Overview');
        $this->assertSelectorTextContains('.admin-user-info', 'Welcome back, Admin');
        $this->assertSelectorTextContains('body', 'Total Customers');
        $this->assertSelectorTextContains('body', 'Total Staff');
        $this->assertSelectorTextContains('body', 'Total Orders');
        $this->assertSelectorTextContains('body', 'Active Products');
        $this->assertSelectorTextContains('body', 'Inactive Products');
        $this->assertSelectorTextContains('body', 'Recent Orders');
        $this->assertSelectorTextContains('body', 'Recent Appointments');
        $this->assertSelectorTextContains('body', 'Recent Promos');
        $this->assertSelectorCount(3, '.management-section');
        $this->assertSelectorCount(3, '.management-overview > .management-section:nth-child(2) .management-count-card');
        $this->assertSelectorCount(3, '.dashboard-activity-panel');
        $this->assertSelectorNotExists('[data-dashboard-count="services"]');
        $this->assertSelectorNotExists('[data-dashboard-count="promos"]');
        self::assertStringNotContainsString('System Summary', $crawler->filter('.management-overview')->text());
        self::assertStringNotContainsString('View site', $crawler->filter('.admin-sidebar')->text());
        $this->assertSelectorNotExists('footer');
    }

    public function testNonAdministratorsCannotAccessAdminPagesOrStatistics(): void
    {
        $client = static::createClient();
        $customer = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($customer);
        $client->loginUser($customer);

        $client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/admin/dashboard/stats');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testProductAndServiceManagementKeepsLegacyFiltersAndDetails(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/products');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#productSearch');
        $this->assertSelectorExists('#productStatus option[value="1"]');
        $this->assertSelectorExists('#productStatus option[value="0"]');
        $this->assertSelectorExists('#productDetails');
        $this->assertSelectorExists('#productFormDialog form#productAdminForm');
        $this->assertSelectorExists('#productCreate[href="/admin/products/new"]');
        $this->assertSelectorExists('#productFormDialog input[name="product[name]"].form-control');
        $this->assertSelectorExists('#productFormDialog button[type="submit"]');
        $this->assertSelectorExists('button.product-view');

        $client->request('GET', '/admin/services');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#serviceSearch');
        $this->assertSelectorExists('#serviceStatus option[value="1"]');
        $this->assertSelectorExists('#serviceStatus option[value="0"]');
        $this->assertSelectorExists('#serviceDetails');
        $this->assertSelectorExists('#serviceFormDialog form#serviceAdminForm');
        $this->assertSelectorExists('button.service-view');

        $client->request('GET', '/admin/services/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('select[name="service[active]"]');
        $this->assertSelectorTextContains('select[name="service[active]"]', 'Active');
        $this->assertSelectorTextContains('select[name="service[active]"]', 'Inactive');
    }

    public function testServiceEditUpdatesAsynchronouslyAndReturnsValidationErrors(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $service = (new Service())
            ->setName('Service Edit Test')
            ->setPrice('20.00')
            ->setDescription('Original description')
            ->setImage('uploads/original.webp');
        $entityManager->persist($service);
        $entityManager->flush();
        $serviceId = $service->getId();

        try {
            $crawler = $client->request('GET', '/admin/services');
            $this->assertResponseIsSuccessful();
            $token = $crawler->filter('#serviceAdminForm input[name="service[_token]"]')->attr('value');
            self::assertNotEmpty($token);
            $this->assertSelectorExists('#serviceFormDialog.admin-edit-dialog');
            $this->assertSelectorExists('#serviceAdminForm .admin-edit-form-grid');

            $client->xmlHttpRequest('POST', '/admin/services/'.$serviceId.'/edit', ['service' => [
                '_token' => $token,
                'name' => 'Updated Service',
                'price' => '49.95',
                'description' => 'Updated description',
                'active' => '0',
                'featured' => '1',
            ]]);
            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertSame('Service updated.', $result['message']);
            self::assertSame($serviceId, $result['service']['id']);
            self::assertSame('49.95', $result['service']['price']);
            self::assertFalse($result['service']['active']);
            self::assertTrue($result['service']['featured']);
            self::assertSame('/uploads/original.webp', $result['service']['imageUrl']);

            $entityManager->clear();
            $service = $container->get(\App\Repository\ServiceRepository::class)->find($serviceId);
            self::assertNotNull($service);
            self::assertSame('Updated Service', $service->getName());
            self::assertSame('Updated description', $service->getDescription());
            self::assertSame('49.95', $service->getPrice());

            $client->xmlHttpRequest('POST', '/admin/services/'.$serviceId.'/edit', ['service' => [
                '_token' => $token,
                'name' => 'Invalid Update',
                'price' => 'not-a-price',
                'description' => 'This update should not persist.',
                'active' => '1',
            ]]);
            $this->assertResponseStatusCodeSame(422);
            $error = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertNotEmpty($error['message']);

            $entityManager->clear();
            $service = $container->get(\App\Repository\ServiceRepository::class)->find($serviceId);
            self::assertNotNull($service);
            self::assertSame('Updated Service', $service->getName());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testServiceFormMapsLegacyActiveStatusWithoutDatabaseWrites(): void
    {
        static::createClient();
        $service = new Service();
        $form = static::getContainer()->get('form.factory')->create(ServiceType::class, $service, [
            'csrf_protection' => false,
        ]);
        $form->submit([
            'name' => 'Test Service',
            'price' => '123.45',
            'description' => 'A service used for a non-persistent form test.',
            'active' => '0',
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $this->assertFalse($service->isActive());
        $this->assertSame('Available', $service->getStatus());
        $this->assertSame('123.45', $service->getPrice());
    }

    public function testUserManagementHasLegacyFiltersDetailsAndPhoneValidation(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/users');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#userSearch');
        $this->assertSelectorExists('#userRole');
        $this->assertSelectorExists('dialog#userDetails');
        $this->assertSelectorExists('button.view-user');
        $this->assertSelectorExists('#panel-action-confirmation-modal');
        $this->assertSelectorExists('form[action^="/admin/users/"][data-deferred-action="delete"]');

        $client->request('GET', '/admin/users/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="admin_user[phone]"][required][pattern="09[0-9]{9}"]');
    }

    public function testAdminCanCreateStaffAndUpdateItsAccount(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $username = uniqid('admin-staff-');

        try {
            $crawler = $client->request('GET', '/admin/users/new');
            $form = $crawler->selectButton('Save')->form();
            $form['admin_user[firstName]'] = 'Created';
            $form['admin_user[lastName]'] = 'Staff';
            $form['admin_user[username]'] = $username;
            $form['admin_user[email]'] = $username.'@example.test';
            $form['admin_user[phone]'] = '09123456789';
            $form['admin_user[role]'] = 'staff';
            $form['admin_user[plainPassword]'] = 'SecureTestPassword123';
            $client->submit($form);

            $this->assertResponseRedirects('/admin/users');
            $staff = $container->get(UserRepository::class)->findOneBy(['username' => $username]);
            self::assertNotNull($staff);
            self::assertSame('staff', $staff->getRole());
            self::assertTrue($container->get(UserPasswordHasherInterface::class)->isPasswordValid($staff, 'SecureTestPassword123'));

            $crawler = $client->request('GET', '/admin/users/'.$staff->getId().'/edit');
            $form = $crawler->selectButton('Save')->form();
            $form['admin_user[firstName]'] = 'Updated';
            $form['admin_user[role]'] = 'customer';
            $client->submit($form);

            $this->assertResponseRedirects('/admin/users');
            self::assertSame('Updated', $container->get(UserRepository::class)->find($staff->getId())?->getFirstName());
            self::assertSame('customer', $container->get(UserRepository::class)->find($staff->getId())?->getRole());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
        self::assertNull($container->get(UserRepository::class)->findOneBy(['username' => $username]));
    }

    public function testAdminCanDeleteAProductWithCsrfProtection(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $product = (new Product())
                ->setName('Temporary product for delete test')
                ->setPrice('1.00')
                ->setStock(0);
            $entityManager->persist($product);
            $entityManager->flush();

            $crawler = $client->request('GET', '/admin/products');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/products/'.$product->getId().'/delete"] input[name="_token"]')
                ->attr('value');
            $deleteUrl = '/admin/products/'.$product->getId().'/delete';
            $client->xmlHttpRequest('POST', $deleteUrl, ['_token' => 'invalid-token']);
            $this->assertResponseStatusCodeSame(403);
            self::assertFalse(json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['success']);
            self::assertNotNull($container->get(ProductRepository::class)->find($product->getId()));

            $client->xmlHttpRequest('POST', $deleteUrl, ['_token' => $token]);
            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(['success' => true, 'deleted' => true, 'id' => $product->getId(), 'message' => 'Product deleted.'], $result);
            $entityManager->clear();
            self::assertNull($container->get(ProductRepository::class)->find($product->getId()));

            $client->xmlHttpRequest('POST', $deleteUrl, ['_token' => $token]);
            $this->assertResponseStatusCodeSame(404);
            self::assertFalse(json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['success']);
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminUserDeleteReturnsJsonAndRemovesAnUnlinkedUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $username = 'delete-test-'.bin2hex(random_bytes(5));
            $user = (new \App\Entity\User())
                ->setUsername($username)
                ->setFirstName('Delete')
                ->setLastName('Test')
                ->setEmail($username.'@example.test')
                ->setPassword('unused-test-password')
                ->setRole('customer');
            $entityManager->persist($user);
            $entityManager->flush();
            $userId = $user->getId();

            $crawler = $client->request('GET', '/admin/users');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/users/'.$userId.'/delete"] input[name="_token"]')
                ->attr('value');
            $client->xmlHttpRequest('POST', '/admin/users/'.$userId.'/delete', ['_token' => $token]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
            self::assertTrue($result['deleted']);
            self::assertSame($userId, $result['id']);
            $entityManager->clear();
            self::assertNull($container->get(UserRepository::class)->find($userId));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testDeletingAProductWithOrderHistoryReturnsDeactivationState(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $product = (new Product())
                ->setName('Product retained for order history')
                ->setPrice('10.00')
                ->setStock(1);
            $order = new CustomerOrder($customer, '1 Test Street', 'Cash on Delivery', 'MWO-PRODUCT-HISTORY');
            $item = new OrderItem($customer, $product, 1, '10.00', 'Cash on Delivery', 'MWO-PRODUCT-HISTORY', $order, '10.00');
            $entityManager->persist($product);
            $entityManager->persist($order);
            $entityManager->persist($item);
            $entityManager->flush();
            $productId = $product->getId();
            $itemId = $item->getId();

            $crawler = $client->request('GET', '/admin/products');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/products/'.$productId.'/delete"] input[name="_token"]')
                ->attr('value');
            $client->xmlHttpRequest('POST', '/admin/products/'.$productId.'/delete', ['_token' => $token]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
            self::assertFalse($result['deleted']);
            self::assertFalse($result['active']);
            self::assertSame($productId, $result['id']);
            $entityManager->clear();
            self::assertFalse($container->get(ProductRepository::class)->find($productId)?->isActive());
            self::assertNotNull($entityManager->find(OrderItem::class, $itemId));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminServiceDeleteUsesJsonAndPreservesBookingHistory(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $serviceWithHistory = (new Service())
                ->setName('Service with booking history')
                ->setPrice('20.00');
            $serviceWithoutHistory = (new Service())
                ->setName('Unused service for delete test')
                ->setPrice('30.00');
            $booking = new ServiceBooking(
                $customer,
                $serviceWithHistory,
                'Test motorcycle',
                new \DateTimeImmutable('2026-12-01'),
                new \DateTimeImmutable('2026-12-01 10:00'),
                null,
                null,
                null,
            );
            $entityManager->persist($serviceWithHistory);
            $entityManager->persist($serviceWithoutHistory);
            $entityManager->persist($booking);
            $entityManager->flush();
            $serviceWithHistoryId = $serviceWithHistory->getId();
            $serviceWithoutHistoryId = $serviceWithoutHistory->getId();

            $crawler = $client->request('GET', '/admin/services');
            $this->assertResponseIsSuccessful();
            $historyToken = $crawler
                ->filter('form[action="/admin/services/'.$serviceWithHistoryId.'/delete"] input[name="_token"]')
                ->attr('value');
            $unusedToken = $crawler
                ->filter('form[action="/admin/services/'.$serviceWithoutHistoryId.'/delete"] input[name="_token"]')
                ->attr('value');

            $client->xmlHttpRequest('POST', '/admin/services/'.$serviceWithHistoryId.'/delete', ['_token' => $historyToken]);
            $this->assertResponseIsSuccessful();
            $historyResult = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($historyResult['success']);
            self::assertFalse($historyResult['deleted']);
            self::assertFalse($historyResult['active']);
            $entityManager->clear();
            self::assertFalse($container->get(\App\Repository\ServiceRepository::class)->find($serviceWithHistoryId)?->isActive());

            $client->xmlHttpRequest('POST', '/admin/services/'.$serviceWithoutHistoryId.'/delete', ['_token' => $unusedToken]);
            $this->assertResponseIsSuccessful();
            $unusedResult = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($unusedResult['deleted']);
            self::assertSame($serviceWithoutHistoryId, $unusedResult['id']);
            $entityManager->clear();
            self::assertNull($container->get(\App\Repository\ServiceRepository::class)->find($serviceWithoutHistoryId));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminCanCreateProductFromTheProductForm(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $name = 'Temporary create test '.bin2hex(random_bytes(5));

        try {
            $crawler = $client->request('GET', '/admin/products/new');
            $this->assertResponseIsSuccessful();
            $form = $crawler->selectButton('Save')->form();
            $form['product[name]'] = $name;
            $form['product[price]'] = '18.50';
            $form['product[stock]'] = '8';
            $client->submit($form);

            $this->assertResponseRedirects('/admin/products');
            $product = $container->get(\App\Repository\ProductRepository::class)->findOneBy(['name' => $name]);
            self::assertNotNull($product);
            self::assertSame('18.50', $product->getPrice());
            self::assertSame(8, $product->getStock());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminCanEditProductFromItsExistingValuesAndPersistChanges(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $product = (new Product())
                ->setName('Temporary product for edit test')
                ->setPrice('12.50')
                ->setStock(4)
                ->setActive(true);
            $entityManager->persist($product);
            $entityManager->flush();
            $productId = $product->getId();

            $list = $client->request('GET', '/admin/products');
            $this->assertResponseIsSuccessful();
            $editLink = $list->filter('a.product-edit[data-id="'.$productId.'"]');
            self::assertSame('Temporary product for edit test', $editLink->attr('data-name'));
            self::assertSame('12.50', $editLink->attr('data-price'));
            self::assertSame('4', $editLink->attr('data-stock'));
            self::assertSame('1', $editLink->attr('data-active'));

            $editPage = $client->request('GET', '/admin/products/'.$productId.'/edit');
            $this->assertResponseIsSuccessful();
            $form = $editPage->selectButton('Save')->form();
            $token = $form['product[_token]']->getValue();
            $client->xmlHttpRequest('POST', '/admin/products/'.$productId.'/edit', ['product' => [
                '_token' => $token,
                'name' => 'Updated temporary product',
                'price' => '19.25',
                'stock' => '9',
            ]]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertTrue($result['success']);
            self::assertSame('Product updated.', $result['message']);
            self::assertSame($productId, $result['product']['id']);
            self::assertSame('19.25', $result['product']['price']);
            self::assertSame(9, $result['product']['stock']);
            self::assertFalse($result['product']['active']);

            $entityManager->clear();
            $updatedProduct = $container->get(ProductRepository::class)->find($productId);
            self::assertNotNull($updatedProduct);
            self::assertSame('Updated temporary product', $updatedProduct->getName());
            self::assertSame('19.25', $updatedProduct->getPrice());
            self::assertSame(9, $updatedProduct->getStock());
            self::assertFalse($updatedProduct->isActive());

            $client->xmlHttpRequest('POST', '/admin/products/'.$productId.'/edit', ['product' => [
                '_token' => $token,
                'name' => 'Invalid product update',
                'price' => 'not-a-price',
                'stock' => '10',
                'active' => '1',
            ]]);
            $this->assertResponseStatusCodeSame(422);
            $error = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertNotEmpty($error['message']);

            $entityManager->clear();
            $updatedProduct = $container->get(ProductRepository::class)->find($productId);
            self::assertNotNull($updatedProduct);
            self::assertSame('Updated temporary product', $updatedProduct->getName());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testPromotionEditFormPreservesOptionsAndIconFields(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/promos');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#promoSearch');
        $this->assertSelectorExists('#promoStatus');
        $this->assertSelectorExists('button.view-promo');
        $this->assertSelectorExists('#promoFormDialog form#promoAdminForm');
        $this->assertSelectorExists('a[href="/admin/promo-history"]');
        $this->assertSelectorNotExists('dialog#promoHistoryDialog');
        $this->assertSelectorExists('#admin-toast-container[popover="manual"]');
        $this->assertSelectorExists('a[aria-current="page"][href="/admin/promos"]');
        $this->assertSelectorNotExists('a[aria-current="page"][href="/admin/promo-history"]');
        $this->assertSelectorExists('#promoAdminForm select[name="promo[active]"] option[value="1"]');
        $this->assertSelectorExists('#promoAdminForm select[name="promo[active]"] option[value="0"]');
        $this->assertSelectorNotExists('form[action^="/admin/promos/"][action$="/status"]');

        $client->request('GET', '/admin/promos/1/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('textarea[name="promo[itemOptions]"]', 'Michelin Pilot Street 2');
        $this->assertSelectorExists('input[name="promo[productIcon]"]');
        $this->assertSelectorExists('input[name="promo[serviceIcon]"]');
    }

    public function testAdminEditsPromotionStatusThroughTheEditFormWithoutRedirect(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $promo = $container->get(PromoRepository::class)->find(1);
            self::assertNotNull($promo);
            $nextActive = !$promo->isActive();
            $crawler = $client->request('GET', '/admin/promos');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('#promoAdminForm input[name="promo[_token]"]')
                ->attr('value');
            $promoId = $promo->getId();
            $formData = [
                '_token' => $token,
                'slug' => $promo->getSlug(),
                'title' => $promo->getTitle(),
                'badge' => $promo->getBadge(),
                'description' => $promo->getDescription(),
                'itemLabel' => $promo->getItemLabel(),
                'itemOptions' => $promo->getItemOptionsText(),
                'perks' => $promo->getPerks() ?? '',
                'productImage' => $promo->getProductImage() ?? '',
                'serviceImage' => $promo->getServiceImage() ?? '',
                'productIcon' => $promo->getStoredProductIcon() ?? '',
                'serviceIcon' => $promo->getStoredServiceIcon() ?? '',
            ];

            foreach ([$nextActive, !$nextActive] as $active) {
                $formData['active'] = $active ? '1' : '0';
                $client->xmlHttpRequest('POST', '/admin/promos/'.$promoId.'/edit', ['promo' => $formData]);

                $this->assertResponseIsSuccessful();
                $result = json_decode((string) $client->getResponse()->getContent(), true);
                self::assertSame($active, $result['promo']['active']);
                self::assertSame('Promotion updated.', $result['message']);
                $entityManager->clear();
                self::assertSame($active, $container->get(PromoRepository::class)->find($promoId)?->isActive());
            }
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminCreatesPromotionFromTheListModalWithoutReload(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $crawler = $client->request('GET', '/admin/promos');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('#promoAdminForm input[name="promo[_token]"]')
                ->attr('value');

            $client->xmlHttpRequest('POST', '/admin/promos/new', ['promo' => [
                '_token' => $token,
                'slug' => 'admin-ajax-create-test',
                'title' => 'Admin AJAX Create Test',
                'badge' => '10% OFF',
                'description' => 'Created from the promotion list modal.',
                'itemLabel' => 'Choose an option',
                'itemOptions' => "Option A\nOption B",
                'perks' => '',
                'productImage' => '',
                'serviceImage' => '',
                'productIcon' => '',
                'serviceIcon' => '',
                'active' => '1',
            ]]);

            $this->assertResponseStatusCodeSame(201);
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertSame('Promotion created.', $result['message']);
            self::assertSame('Admin AJAX Create Test', $result['promo']['title']);
            self::assertSame('/admin/promos/'.$result['promo']['id'].'/edit', $result['promo']['editUrl']);
            self::assertSame('/admin/promos/'.$result['promo']['id'].'/delete', $result['promo']['deleteUrl']);
            self::assertNotSame('', $result['deleteToken']);
            self::assertSame('admin-ajax-create-test', $container->get(PromoRepository::class)->find($result['promo']['id'])?->getSlug());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdministratorCannotRemoveTheirOwnAdministratorRole(): void
    {
        $client = static::createClient();
        $users = static::getContainer()->get(UserRepository::class);
        $admin = $users->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/users/2/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['admin_user[role]'] = 'customer';
        $client->submit($form);

        $this->assertResponseRedirects('/admin/users');
        $this->assertSame('admin', $users->find(2)?->getRole());
    }

    public function testAdminAppointmentFormsOnlyOfferSupportedStatuses(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/appointments/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('select[name="form[status]"] option[value="Pending"]');
        $this->assertSelectorExists('select[name="form[status]"] option[value="Completed"]');
        $this->assertSelectorExists('select[name="form[status]"] option[value="Cancelled"]');
        $this->assertSelectorNotExists('select[name="form[status]"] option[value="Confirmed"]');
        $this->assertSelectorNotExists('select[name="form[status]"] option[value="Rejected"]');

        $booking = null;
        foreach (static::getContainer()->get(ServiceBookingRepository::class)->findForAdmin('', '') as $candidate) {
            if (!in_array($candidate->getStatus(), ['Completed', 'Cancellation Pending'], true)) {
                $booking = $candidate;
                break;
            }
        }
        self::assertNotNull($booking);
        $client->request('GET', '/admin/appointments/'.$booking->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('select[name="service_booking[status]"] option[value="Pending"]');
        $this->assertSelectorExists('select[name="service_booking[status]"] option[value="Completed"]');
        $this->assertSelectorExists('select[name="service_booking[status]"] option[value="Cancelled"]');
        $this->assertSelectorNotExists('select[name="service_booking[status]"] option[value="Confirmed"]');
        $this->assertSelectorNotExists('select[name="service_booking[status]"] option[value="Rejected"]');
        $expectedStatus = match ($booking->getStatus()) {
            'Completed' => 'Completed',
            'Cancelled', 'Rejected' => 'Cancelled',
            default => 'Pending',
        };
        $this->assertSelectorExists('select[name="service_booking[status]"] option[value="'.$expectedStatus.'"][selected]');
    }

    public function testAdminAppointmentFiltersAndSummaryUseDoctrineQueries(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/appointments', ['search' => 'Honda', 'status' => 'Pending']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action="/admin/appointments"]');
        self::assertSame(11, $client->getCrawler()->filter('#appointmentTable thead th')->count());
        $this->assertSelectorTextContains('thead', 'Appointment ID');
        $this->assertSelectorTextContains('thead', 'Date / Time');
        $this->assertSelectorExists('#appointmentTable td.appointment-date-time span');
        self::assertStringNotContainsString('Bundle', $client->getCrawler()->filter('#appointmentTable thead')->text());
        self::assertStringNotContainsString('Booking Source', $client->getCrawler()->filter('#appointmentTable thead')->text());
        $this->assertSelectorNotExists('form[action^="/admin/appointments/"][action$="/status"]');
        $this->assertSelectorExists('#addAppointment[data-form-url="/admin/appointments/new"]');
        $this->assertSelectorExists('#appointmentActionDialog');
        $this->assertSelectorExists('a.edit-appointment[data-form-url^="/admin/appointments/"][data-form-url$="/edit"]');
        $this->assertSelectorExists('button.view-appointment.btn-secondary');
        $this->assertSelectorExists('a.edit-appointment.btn-info');
        $this->assertSelectorExists('form[data-deferred-action="delete"] button.btn-danger');
        $this->assertSelectorTextContains('tbody', 'Honda Click');
        $this->assertSelectorTextContains('body', 'All Appointments');
    }

    public function testAdminCreatesAppointmentFromTheModalAndPersistsIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $countsBeforeCreate = $container->get(ServiceBookingRepository::class)->getAdminStatusCounts();
            $client->xmlHttpRequest('GET', '/admin/appointments/new');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#appointmentActionForm select[name="form[customerId]"]');
            $this->assertSelectorExists('#appointmentActionForm select[name="form[serviceId]"]');
            $token = $client->getCrawler()
                ->filter('#appointmentActionForm input[name="form[_token]"]')
                ->attr('value');
            $service = $container->get(\App\Repository\ServiceRepository::class)->findActiveServices()[0] ?? null;
            self::assertNotNull($service);

            $client->xmlHttpRequest('POST', '/admin/appointments/new', ['form' => [
                '_token' => $token,
                'customerId' => '3',
                'serviceId' => (string) $service->getId(),
                'vehicleModel' => 'Modal Test Motorcycle',
                'bookingDate' => '2026-11-20',
                'bookingTime' => '10:30',
                'status' => 'Pending',
                'notes' => 'Created from the appointment modal.',
            ]]);

            $this->assertResponseStatusCodeSame(201);
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertSame('Appointment created.', $result['message']);
            self::assertSame('Modal Test Motorcycle', $result['booking']['vehicle']);
            self::assertSame('Pending', $result['booking']['status']);
            self::assertSame($countsBeforeCreate['total'] + 1, $result['counts']['total']);
            self::assertSame($countsBeforeCreate['pending'] + 1, $result['counts']['pending']);
            self::assertSame('/admin/appointments/'.$result['booking']['id'].'/edit', $result['booking']['editUrl']);
            self::assertSame('Modal Test Motorcycle', $container->get(ServiceBookingRepository::class)->find($result['booking']['id'])?->getVehicleModel());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminAppointmentDeleteReturnsJsonAndUpdatesCounts(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $countsBefore = $container->get(ServiceBookingRepository::class)->getAdminStatusCounts();
            $service = (new Service())->setName('Appointment delete service')->setPrice('25.00');
            $booking = new ServiceBooking(
                $customer,
                $service,
                'Test motorcycle',
                new \DateTimeImmutable('2026-12-02'),
                new \DateTimeImmutable('2026-12-02 11:00'),
                null,
                null,
                null,
            );
            $entityManager->persist($service);
            $entityManager->persist($booking);
            $entityManager->flush();
            $bookingId = $booking->getId();

            $crawler = $client->request('GET', '/admin/appointments');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/appointments/'.$bookingId.'/delete"] input[name="_token"]')
                ->attr('value');
            $client->xmlHttpRequest('POST', '/admin/appointments/'.$bookingId.'/delete', ['_token' => $token]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
            self::assertTrue($result['deleted']);
            self::assertSame($bookingId, $result['id']);
            self::assertSame($countsBefore, $result['counts']);
            $entityManager->clear();
            self::assertNull($container->get(ServiceBookingRepository::class)->find($bookingId));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminEditsAppointmentStatusFromTheModalAndPersistsIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $booking = null;
            foreach ($container->get(ServiceBookingRepository::class)->findForAdmin('', '') as $candidate) {
                if (!in_array($candidate->getStatus(), ['Completed', 'Cancellation Pending'], true)) {
                    $booking = $candidate;
                    break;
                }
            }
            self::assertNotNull($booking);
            $booking->setStatus('Confirmed');
            $entityManager->flush();
            $bookingId = $booking->getId();

            $client->xmlHttpRequest('GET', '/admin/appointments/'.$bookingId.'/edit');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#appointmentActionForm select[name="service_booking[status]"]');
            $this->assertSelectorExists('#appointmentActionForm select[name="service_booking[status]"] option[value="Pending"][selected]');
            $token = $client->getCrawler()
                ->filter('#appointmentActionForm input[name="service_booking[_token]"]')
                ->attr('value');
            $editData = [
                '_token' => $token,
                'service' => (string) $booking->getService()->getId(),
                'vehicleModel' => $booking->getVehicleModel(),
                'bookingDate' => $booking->getBookingDate()->format('Y-m-d'),
                'bookingTime' => $booking->getBookingTime()->format('H:i'),
                'status' => 'Confirmed',
                'notes' => $booking->getNotes() ?? '',
                'rejectionReason' => '',
            ];
            $client->xmlHttpRequest('POST', '/admin/appointments/'.$bookingId.'/edit', ['service_booking' => $editData]);
            $this->assertResponseStatusCodeSame(422);
            $entityManager->clear();
            $booking = $container->get(ServiceBookingRepository::class)->find($bookingId);
            self::assertNotNull($booking);
            $nextStatus = $booking->getStatus() === 'Cancelled' ? 'Pending' : 'Cancelled';

            $client->xmlHttpRequest('POST', '/admin/appointments/'.$bookingId.'/edit', ['service_booking' => [
                '_token' => $token,
                'service' => (string) $booking->getService()->getId(),
                'vehicleModel' => $booking->getVehicleModel(),
                'bookingDate' => $booking->getBookingDate()->format('Y-m-d'),
                'bookingTime' => $booking->getBookingTime()->format('H:i'),
                'status' => $nextStatus,
                'notes' => $booking->getNotes() ?? '',
                'rejectionReason' => '',
            ]]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertSame('Appointment updated.', $result['message']);
            self::assertSame($nextStatus, $result['booking']['status']);
            self::assertSame($container->get(ServiceBookingRepository::class)->getAdminStatusCounts(), $result['counts']);
            $entityManager->clear();
            self::assertSame($nextStatus, $container->get(ServiceBookingRepository::class)->find($bookingId)?->getStatus());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminUserListProvidesCreateAndModalEditForms(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $user = $client->getContainer()->get(UserRepository::class)->find(3);
            self::assertNotNull($user);
            $crawler = $client->request('GET', '/admin/users');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('details.admin-user-create form[action="/admin/users/new"]');
            $this->assertSelectorExists('dialog#userEditDialog form#userEditForm');
            $this->assertSelectorExists('dialog#userEditDialog select[name="admin_user[role]"]');
            $this->assertSelectorExists('#userEditForm .admin-edit-form-grid input[name="admin_user[firstName]"]');
            $this->assertSelectorExists('a.user-edit[data-action^="/admin/users/"][data-user]');

            $token = $crawler->filter('#userEditForm input[name="admin_user[_token]"]')->attr('value');
            $updatedFirstName = 'Updated '.$user->getFirstName();
            $client->xmlHttpRequest('POST', '/admin/users/'.$user->getId().'/edit', [
                'admin_user' => [
                    '_token' => $token,
                    'firstName' => $updatedFirstName,
                    'lastName' => $user->getLastName(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhone(),
                    'role' => 'staff',
                    'plainPassword' => '',
                ],
            ]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
            self::assertSame($user->getId(), $result['user']['id']);
            self::assertSame($updatedFirstName, $result['user']['firstName']);
            $entityManager->clear();
            $updatedUser = $client->getContainer()->get(UserRepository::class)->find($user->getId());
            self::assertNotNull($updatedUser);
            self::assertSame('staff', $updatedUser?->getRole());
            self::assertSame($updatedFirstName, $updatedUser->getFirstName());

            $client->xmlHttpRequest('POST', '/admin/users/'.$user->getId().'/edit', [
                'admin_user' => [
                    '_token' => $token,
                    'firstName' => '',
                    'lastName' => $updatedUser->getLastName(),
                    'username' => $updatedUser->getUsername(),
                    'email' => $updatedUser->getEmail(),
                    'phone' => $updatedUser->getPhone(),
                    'role' => 'staff',
                    'plainPassword' => '',
                ],
            ]);
            $this->assertResponseStatusCodeSame(422);
            $error = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertNotEmpty($error['message']);
            $entityManager->clear();
            self::assertSame($updatedFirstName, $client->getContainer()->get(UserRepository::class)->find($user->getId())?->getFirstName());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminOrdersListShowsPersistedOrderLinesAndTotals(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/orders');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('tbody', 'Motorcycle Helmet');
        $this->assertSelectorTextContains('tbody', 'H3420 Side Mirror');
        $this->assertSelectorTextContains('tbody', '₱1,500.00');
        $this->assertSelectorExists('button[data-open-order-details]');
        $this->assertSelectorExists('button[data-order-create-url="/admin/orders/new"]');
        $this->assertSelectorExists('dialog#orderCreateDialog.admin-edit-dialog');
        $this->assertSelectorExists('dialog[id^="order-view-details-"].order-details-modal');
        $this->assertSelectorExists('dialog[id^="order-edit-details-"].admin-order-edit-dialog');
        $this->assertSelectorNotExists('dialog[id^="order-view-details-"] form');
        $this->assertSelectorNotExists('dialog[id^="order-view-details-"] input, dialog[id^="order-view-details-"] select, dialog[id^="order-view-details-"] textarea');
        $this->assertSelectorExists('dialog[id^="order-edit-details-"] form.order-status-form');
        $this->assertSelectorNotExists('dialog[id^="order-edit-details-"] form.order-payment-form');
        $this->assertSelectorNotExists('[data-confirm-payment]');
        self::assertStringNotContainsString('verify a pending payment', (string) $client->getResponse()->getContent());
        $this->assertSelectorExists('form[action^="/admin/orders/"][action$="/delete"][data-deferred-action="delete"]');
        $this->assertSelectorExists('#orderStatus option[value="Pending"]');
        $this->assertSelectorExists('#orderStatus option[value="Completed"]');
        $this->assertSelectorExists('#orderStatus option[value="Cancelled"]');
        $this->assertSelectorNotExists('#orderStatus option[value="Processing"]');
        $this->assertSelectorExists('dialog[id^="order-edit-details-"] select[name="status"] option[value="Pending"]');
        $this->assertSelectorExists('dialog[id^="order-edit-details-"] select[name="status"] option[value="Completed"]');
        $this->assertSelectorExists('dialog[id^="order-edit-details-"] select[name="status"] option[value="Cancelled"]');
        $this->assertSelectorNotExists('dialog[id^="order-edit-details-"] select[name="status"] option[value="Processing"]');
        $this->assertSelectorNotExists('.order-filters button[type="submit"]');
        $this->assertSelectorExists('#orderStatus[data-auto-submit]');
    }

    public function testAdminCreatesOrderFromCenteredModalAndPersistsIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $product = (new Product())
                ->setName('Admin modal order product')
                ->setPrice('12.50')
                ->setStock(8)
                ->setActive(true);
            $entityManager->persist($product);
            $entityManager->flush();
            $productId = $product->getId();

            $client->xmlHttpRequest('GET', '/admin/orders/new');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#orderCreateForm select[name="order_create[customerId]"]');
            $this->assertSelectorExists('#orderCreateForm select[name="order_create[items][0][productId]"]');
            $this->assertSelectorTextContains(
                sprintf('#orderCreateForm select[name="order_create[items][0][productId]"] option[value="%d"]', $productId),
                'Admin modal order product — ₱12.50',
            );
            $token = $client->getCrawler()->filter('#orderCreateForm input[name="order_create[_token]"]')->attr('value');

            $client->xmlHttpRequest('POST', '/admin/orders/new', ['order_create' => [
                '_token' => $token,
                'customerId' => (string) $customer->getId(),
                'deliveryAddress' => '42 Modal Test Avenue',
                'items' => [
                    0 => ['productId' => (string) $productId, 'quantity' => '9'],
                ],
            ]]);
            $this->assertResponseStatusCodeSame(409);
            self::assertSame(8, $container->get(ProductRepository::class)->find($productId)?->getStock());

            $client->xmlHttpRequest('POST', '/admin/orders/new', ['order_create' => [
                '_token' => $token,
                'customerId' => (string) $customer->getId(),
                'deliveryAddress' => '42 Modal Test Avenue',
                'items' => [
                    0 => ['productId' => (string) $productId, 'quantity' => '2'],
                ],
            ]]);

            $this->assertResponseStatusCodeSame(201);
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertTrue($result['success']);
            self::assertSame('Order created.', $result['message']);
            $createdOrder = $container->get(CustomerOrderRepository::class)->find($result['id']);
            self::assertNotNull($createdOrder);
            self::assertSame('42 Modal Test Avenue', $createdOrder->getDeliveryAddress());
            self::assertSame(6, $container->get(\App\Repository\ProductRepository::class)->find($productId)?->getStock());
            $createdOrderId = (int) $createdOrder->getId();
            $itemsByGroup = $container->get(CustomerOrderRepository::class)->findItemsForGroups([$createdOrderId]);
            self::assertCount(1, $itemsByGroup[$createdOrderId] ?? []);
            self::assertSame('Admin modal order product', $itemsByGroup[$createdOrderId][0]['productName']);
            self::assertSame(12.5, (float) $itemsByGroup[$createdOrderId][0]['unitPrice']);
            self::assertSame(25.0, (float) $itemsByGroup[$createdOrderId][0]['totalAmount']);

            $client->request('GET', '/admin/orders');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('.order-items', 'Admin modal order product × 2');
            $this->assertSelectorTextContains('.order-items', '₱25.00');
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminOrderStatusUpdatesAreAjaxAndRejectLegacyStatuses(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $order = new CustomerOrder($customer, 'Test address', 'Cash on Delivery', '', 'MWO-ADMIN-STATUS-'.uniqid());
            $order->setStatus('Pending');
            $entityManager->persist($order);
            $entityManager->flush();
            $orderId = $order->getId();

            $crawler = $client->request('GET', '/admin/orders', ['search' => (string) $orderId]);
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/orders/'.$orderId.'/status"] input[name="_token"]')
                ->attr('value');

            $client->xmlHttpRequest('POST', '/admin/orders/'.$orderId.'/status', [
                '_token' => $token,
                'status' => 'Completed',
            ]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
            self::assertSame($orderId, $result['id']);
            self::assertSame('Completed', $result['status']);
            self::assertSame($container->get(CustomerOrderRepository::class)->getStatusCounts(), $result['counts']);
            self::assertSame('Completed', $container->get(CustomerOrderRepository::class)->find($orderId)?->getStatus());

            $client->xmlHttpRequest('POST', '/admin/orders/'.$orderId.'/status', [
                '_token' => $token,
                'status' => 'Processing',
            ]);

            $this->assertResponseStatusCodeSame(422);
            $invalidResult = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertFalse($invalidResult['success']);
            self::assertSame('Completed', $container->get(CustomerOrderRepository::class)->find($orderId)?->getStatus());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminCanFilterLegacyProcessingOrdersAsPending(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $order = new CustomerOrder($customer, 'Test address', 'Cash on Delivery', '');
            $order->setStatus('Processing');
            $entityManager->persist($order);
            $entityManager->flush();

            $client->request('GET', '/admin/orders', [
                'status' => 'Pending',
                'search' => (string) $order->getId(),
            ]);

            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#orderStatus option[value="Pending"][selected]');
            $this->assertSelectorTextContains('tbody', '#'.$order->getId());
            $this->assertSelectorTextContains('tbody', 'Pending');
            $this->assertSelectorExists('dialog#order-edit-details-'.$order->getId().' select[name="status"] option[value="Pending"][selected]');
            $this->assertSelectorExists('dialog#order-edit-details-'.$order->getId().' select[name="status"][data-current-status="Processing"][data-initial-status="Pending"]');
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminDashboardShowsFiveLatestDatabaseRecordsAndProductStatusCounts(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $orders = $container->get(\App\Repository\CustomerOrderRepository::class)->findRecentForAdmin();
        $appointments = $container->get(ServiceBookingRepository::class)->findRecentForAdmin();
        $promoClaims = $container->get(PromoClaimRepository::class)->findRecentForAdmin();
        self::assertLessThanOrEqual(5, count($orders));
        self::assertLessThanOrEqual(5, count($appointments));
        self::assertLessThanOrEqual(5, count($promoClaims));
        for ($index = 1; $index < count($orders); ++$index) {
            $previous = $orders[$index - 1];
            $current = $orders[$index];
            self::assertGreaterThanOrEqual($current['createdAt'], $previous['createdAt']);
            if ($previous['createdAt'] == $current['createdAt']) {
                self::assertGreaterThan($current['orderGroupId'], $previous['orderGroupId']);
            }
        }
        for ($index = 1; $index < count($appointments); ++$index) {
            $previous = $appointments[$index - 1];
            $current = $appointments[$index];
            $previousCreatedAt = $previous->getCreatedAt()?->getTimestamp() ?? 0;
            $currentCreatedAt = $current->getCreatedAt()?->getTimestamp() ?? 0;
            self::assertGreaterThanOrEqual($currentCreatedAt, $previousCreatedAt);
            if ($previousCreatedAt === $currentCreatedAt) {
                self::assertGreaterThan($current->getId(), $previous->getId());
            }
        }
        for ($index = 1; $index < count($promoClaims); ++$index) {
            $previous = $promoClaims[$index - 1];
            $current = $promoClaims[$index];
            self::assertGreaterThanOrEqual($current->getCreatedAt(), $previous->getCreatedAt());
            if ($previous->getCreatedAt() == $current->getCreatedAt()) {
                self::assertGreaterThan($current->getId(), $previous->getId());
            }
        }

        $crawler = $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        foreach ($orders as $order) {
            $this->assertSelectorTextContains('#dashboard-activity', 'Order #'.$order['orderGroupId']);
        }
        foreach ($appointments as $appointment) {
            $this->assertSelectorTextContains('#dashboard-activity', 'Appointment #'.$appointment->getId());
        }
        foreach ($promoClaims as $claim) {
            $this->assertSelectorTextContains('#dashboard-activity', 'Promo #'.$claim->getId());
        }

        $client->request('GET', '/admin/dashboard/stats');
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsString($payload['activityHtml']);
        self::assertSame($container->get(\App\Repository\ProductRepository::class)->getStatusCounts()['active'], $payload['counts']['productsActive']);
    }

    public function testAdminCanDeletePendingOrderAndRestoreItsStock(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $product = (new Product())
                ->setName('Temporary order deletion product')
                ->setPrice('10.00')
                ->setStock(3);
            $order = new CustomerOrder($customer, '1 Test Street', 'Cash on Delivery', 'MWO-DELETE-TEST');
            $item = new OrderItem($customer, $product, 2, '20.00', 'Cash on Delivery', 'MWO-DELETE-TEST', $order, '10.00');
            $entityManager->persist($product);
            $entityManager->persist($order);
            $entityManager->persist($item);
            $entityManager->flush();
            $orderId = $order->getId();
            $itemId = $item->getId();
            $productId = $product->getId();

            $crawler = $client->request('GET', '/admin/orders');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/orders/'.$orderId.'/delete"] input[name="_token"]')
                ->attr('value');
            $countsBeforeDelete = $container->get(CustomerOrderRepository::class)->getStatusCounts();
            $client->xmlHttpRequest('POST', '/admin/orders/'.$orderId.'/delete', ['_token' => $token]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
            self::assertTrue($result['deleted']);
            self::assertSame($orderId, $result['id']);
            self::assertSame($countsBeforeDelete['total'] - 1, $result['counts']['total']);
            $entityManager->clear();
            self::assertNull($entityManager->find(CustomerOrder::class, $orderId));
            self::assertNull($entityManager->find(OrderItem::class, $itemId));
            self::assertSame(5, $entityManager->find(Product::class, $productId)?->getStock());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminPromoHistoryHasItsOwnPage(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/promo-history');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.main-header h2');
        $this->assertSelectorTextContains('.main-header', 'Promotion History');
        $this->assertSelectorNotExists('dialog#promoHistoryDialog');
        $this->assertSelectorExists('a[aria-current="page"][href="/admin/promo-history"]');
        $this->assertSelectorNotExists('a[aria-current="page"][href="/admin/promos"]');
        $this->assertSelectorExists('#promoClaimSearch');
        $this->assertSelectorExists('#promoClaimStatus');
        $this->assertSelectorExists('#promoClaimStatus option[value="Pending"]');
        $this->assertSelectorExists('#promoClaimStatus option[value="Completed"]');
        $this->assertSelectorExists('#promoClaimStatus option[value="Cancelled"]');
        $this->assertSelectorNotExists('#promoClaimStatus option[value="Confirmed"]');
        $this->assertSelectorNotExists('#promoClaimStatus option[value="Used"]');
        $this->assertSelectorExists('table#promoClaimTable.admin-table');
        $this->assertSelectorExists('button.view-promo-claim');
        $this->assertSelectorExists('dialog#promoClaimDetails');
        $this->assertSelectorExists('button#addPromoClaim[data-form-url="/admin/promo-history/new"]');
        $this->assertSelectorExists('dialog#promoClaimCreateDialog.admin-edit-dialog');
        $this->assertSelectorExists('dialog#promoClaimDetails.modal-content');
        $this->assertSelectorExists('a.edit-promo-claim[data-action^="/admin/promo-history/"][data-action$="/edit"]');
        $this->assertSelectorExists('dialog#promoClaimEdit form#promoClaimEditForm');
        $this->assertSelectorExists('form[action^="/admin/promo-history/"][data-deferred-action="delete"]');

        $client->request('GET', '/admin/promos');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('#promoClaimTable');
    }

    public function testAdminCreatesPromoClaimFromCenteredModalAndPersistsIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        $customer = $container->get(UserRepository::class)->find(3);
        self::assertNotNull($admin);
        self::assertNotNull($customer);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $promo = (new Promo())
                ->setSlug('admin-modal-claim-'.bin2hex(random_bytes(4)))
                ->setTitle('Admin Modal Claim Promo')
                ->setBadge('Modal')
                ->setDescription('Created from the admin claim modal.')
                ->setItemLabel('Service')
                ->setItemOptions('Oil change'."\n".'Brake check');
            $otherPromo = (new Promo())
                ->setSlug('admin-modal-claim-other-'.bin2hex(random_bytes(4)))
                ->setTitle('Admin Modal Claim Other Promo')
                ->setBadge('Modal')
                ->setDescription('Another active promotion.')
                ->setItemLabel('Service')
                ->setItemOptions('Tire change');
            $entityManager->persist($promo);
            $entityManager->persist($otherPromo);
            $entityManager->flush();

            $client->xmlHttpRequest('GET', '/admin/promo-history/new');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#promoClaimCreateForm select[name="form[customerId]"]');
            $this->assertSelectorExists('#promoClaimCreateForm select[name="form[promoId]"]');
            $this->assertSelectorExists('#promoClaimCreateForm select[name="form[selectedOption]"] option[value="Oil change"]');
            $token = $client->getCrawler()->filter('#promoClaimCreateForm input[name="form[_token]"]')->attr('value');

            $client->xmlHttpRequest('POST', '/admin/promo-history/new', ['form' => [
                '_token' => $token,
                'customerId' => (string) $customer->getId(),
                'promoId' => (string) $promo->getId(),
                'selectedOption' => 'Tire change',
                'appointmentDate' => '2026-12-10',
                'appointmentTime' => '09:30',
            ]]);
            $this->assertResponseStatusCodeSame(422);

            $client->xmlHttpRequest('POST', '/admin/promo-history/new', ['form' => [
                '_token' => $token,
                'customerId' => (string) $customer->getId(),
                'promoId' => (string) $promo->getId(),
                'selectedOption' => 'Oil change',
                'appointmentDate' => '2026-12-10',
                'appointmentTime' => '09:30',
            ]]);

            $this->assertResponseStatusCodeSame(201);
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertTrue($result['success']);
            self::assertSame('Promotion claim created.', $result['message']);
            $claim = $container->get(PromoClaimRepository::class)->find($result['id']);
            self::assertNotNull($claim);
            self::assertSame('Pending', $claim->getStatus());
            self::assertSame('Oil change', $claim->getSelectedOption());
            self::assertSame($promo->getId(), $claim->getPromo()?->getId());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminPromoClaimEditChangesOnlyPromotionAndStatus(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $originalPromo = (new Promo())
                ->setSlug('admin-history-original')
                ->setTitle('Original Admin History Promo')
                ->setBadge('Original')
                ->setDescription('Original promotion description')
                ->setItemLabel('Original bundle')
                ->setItemOptions('Legacy option');
            $replacementPromo = (new Promo())
                ->setSlug('admin-history-replacement')
                ->setTitle('Replacement Admin History Promo')
                ->setBadge('Replacement')
                ->setDescription('Replacement promotion description')
                ->setItemLabel('Replacement bundle')
                ->setItemOptions('Different option');
            $appointmentDate = new \DateTimeImmutable('2026-04-10');
            $appointmentTime = new \DateTimeImmutable('2026-04-10 10:30:00');
            $claim = new PromoClaim(null, 'Legacy Guest', '09123456789', 'admin-history-original', 'Legacy option', $appointmentDate, $appointmentTime);
            $claim->setPromo($originalPromo);
            $claim->setStatus('Confirmed');
            $entityManager->persist($originalPromo);
            $entityManager->persist($replacementPromo);
            $entityManager->persist($claim);
            $entityManager->flush();
            $claimId = $claim->getId();
            $replacementPromoId = $replacementPromo->getId();

            $crawler = $client->request('GET', '/admin/promo-history');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('.promo-claim-row[data-id="'.$claimId.'"][data-status="Pending"]');
            $this->assertSelectorExists('.promo-claim-row[data-id="'.$claimId.'"] .edit-promo-claim[data-status="Pending"]');
            $this->assertSelectorExists('select[name="admin_promo_claim_update[promo]"]');
            $this->assertSelectorExists('select[name="admin_promo_claim_update[status]"]');
            $this->assertSelectorExists('select[name="admin_promo_claim_update[status]"] option[value="Pending"]');
            $this->assertSelectorExists('select[name="admin_promo_claim_update[status]"] option[value="Completed"]');
            $this->assertSelectorExists('select[name="admin_promo_claim_update[status]"] option[value="Cancelled"]');
            $this->assertSelectorNotExists('select[name="admin_promo_claim_update[status]"] option[value="Confirmed"]');
            $this->assertSelectorNotExists('select[name="admin_promo_claim_update[status]"] option[value="Used"]');
            $this->assertSelectorNotExists('[name="admin_promo_claim_update[selectedOption]"]');
            $this->assertSelectorNotExists('[name="admin_promo_claim_update[appointmentDate]"]');
            $this->assertSelectorNotExists('[name="admin_promo_claim_update[appointmentTime]"]');
            $token = $crawler->filter('#promoClaimEditForm input[name="admin_promo_claim_update[_token]"]')->attr('value');
            $client->xmlHttpRequest('POST', '/admin/promo-history/'.$claimId.'/edit', [
                'admin_promo_claim_update' => [
                    '_token' => $token,
                    'promo' => (string) $replacementPromoId,
                    'status' => 'Confirmed',
                ],
            ]);
            $this->assertResponseStatusCodeSame(422);
            $entityManager->clear();
            self::assertSame('Confirmed', $container->get(PromoClaimRepository::class)->find($claimId)?->getStatus());

            $client->xmlHttpRequest('POST', '/admin/promo-history/'.$claimId.'/edit', [
                'admin_promo_claim_update' => [
                    '_token' => $token,
                    'promo' => (string) $replacementPromoId,
                    'status' => 'Pending',
                ],
            ]);

            $this->assertResponseIsSuccessful();
            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('Promotion claim updated.', $response['message']);
            self::assertSame('Replacement Admin History Promo', $response['claim']['promoTitle']);
            $entityManager->clear();
            $updatedClaim = $container->get(PromoClaimRepository::class)->find($claimId);
            self::assertNotNull($updatedClaim);
            self::assertSame('admin-history-replacement', $updatedClaim->getBundleSlug());
            self::assertSame('Pending', $updatedClaim->getStatus());
            self::assertSame('Legacy option', $updatedClaim->getSelectedOption());
            self::assertSame($appointmentDate->format('Y-m-d'), $updatedClaim->getAppointmentDate()->format('Y-m-d'));
            self::assertSame($appointmentTime->format('H:i'), $updatedClaim->getAppointmentTime()->format('H:i'));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testDeletingPromoKeepsItsClaimHistory(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $promo = (new Promo())
                ->setSlug('admin-promo-delete-test')
                ->setTitle('Admin Promo Delete Test')
                ->setBadge('Test')
                ->setDescription('Promotion for delete test')
                ->setItemLabel('Test bundle')
                ->setItemOptions('Test option');
            $claim = new PromoClaim(null, 'Guest Claim', '09123456789', 'admin-promo-delete-test', 'Test option', new \DateTimeImmutable('2026-04-11'), new \DateTimeImmutable('2026-04-11 11:00:00'));
            $claim->setPromo($promo);
            $entityManager->persist($promo);
            $entityManager->persist($claim);
            $entityManager->flush();
            $promoId = $promo->getId();
            $claimId = $claim->getId();

            $crawler = $client->request('GET', '/admin/promos');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/promos/'.$promoId.'/delete"] input[name="_token"]')
                ->attr('value');
            $client->xmlHttpRequest('POST', '/admin/promos/'.$promoId.'/delete', ['_token' => $token]);

            $this->assertResponseIsSuccessful();
            $deleteResult = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($deleteResult['success']);
            self::assertTrue($deleteResult['deleted']);
            self::assertSame($promoId, $deleteResult['id']);
            $entityManager->clear();
            self::assertNull($entityManager->find(Promo::class, $promoId));
            $retainedClaim = $container->get(PromoClaimRepository::class)->find($claimId);
            self::assertNotNull($retainedClaim);
            self::assertNull($retainedClaim->getPromo());
            self::assertSame('admin-promo-delete-test', $retainedClaim->getBundleSlug());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testAdminPromoClaimDeleteReturnsJsonAndRemovesTheClaim(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $admin = $container->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $claim = new PromoClaim(
                null,
                'Delete Test Guest',
                '09123456789',
                'admin-claim-delete-test',
                'Test option',
                new \DateTimeImmutable('2026-12-03'),
                new \DateTimeImmutable('2026-12-03 12:00'),
            );
            $entityManager->persist($claim);
            $entityManager->flush();
            $claimId = $claim->getId();

            $crawler = $client->request('GET', '/admin/promo-history');
            $this->assertResponseIsSuccessful();
            $token = $crawler
                ->filter('form[action="/admin/promo-history/'.$claimId.'/delete"] input[name="_token"]')
                ->attr('value');
            $client->xmlHttpRequest('POST', '/admin/promo-history/'.$claimId.'/delete', ['_token' => $token]);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
            self::assertTrue($result['deleted']);
            self::assertSame($claimId, $result['id']);
            $entityManager->clear();
            self::assertNull($container->get(PromoClaimRepository::class)->find($claimId));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
