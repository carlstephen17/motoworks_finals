<?php

namespace App\Tests\Controller;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\UserRepository;
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
        }
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

        $client->request('GET', '/admin/users/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="admin_user[phone]"][required][pattern="09[0-9]{9}"]');
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

        $client->request('GET', '/admin/promos/1/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('textarea[name="promo[itemOptions]"]', 'Michelin Pilot Street 2');
        $this->assertSelectorExists('input[name="promo[productIcon]"]');
        $this->assertSelectorExists('input[name="promo[serviceIcon]"]');
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

    public function testAdminAppointmentFormIncludesLegacyStatusChoices(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/appointments/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('select[name="form[status]"] option[value="Pending"]');
        $this->assertSelectorExists('select[name="form[status]"] option[value="Cancelled"]');
        $this->assertSelectorExists('select[name="form[status]"] option[value="Completed"]');
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
        $this->assertSelectorTextContains('tbody', 'Honda Click');
        $this->assertSelectorTextContains('body', 'Total:');
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
    }
}
