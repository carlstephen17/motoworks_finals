<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use App\Repository\MotorcycleRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StaffControllerTest extends WebTestCase
{
    public function testStaffWalkInFormsSupportRegisteredAndNewMotorcycles(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->find(6);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff/appointments/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('select[name="form[motorcycleMode]"]');
        $this->assertSelectorExists('select[name="form[motorcycleId]"]');
        $this->assertSelectorExists('input[name="form[motorcycleModel]"]');

        $client->request('GET', '/staff/service-records/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('select[name="form[motorcycleMode]"]');
        $this->assertSelectorExists('select[name="form[motorcycleId]"]');
        $this->assertSelectorExists('input[name="form[plateNumber]"]');
    }

    public function testMotorcycleDuplicateLookupUsesDoctrineMappings(): void
    {
        $customer = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($customer);

        $duplicate = static::getContainer()
            ->get(MotorcycleRepository::class)
            ->findDuplicate($customer, 'Missing test motorcycle '.bin2hex(random_bytes(6)), null);

        self::assertNull($duplicate);
    }

    public function testStaffOrdersListGroupedRecordsAndSupportsMultipleLineItems(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->find(6);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff/orders');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('tbody', 'Motorcycle Helmet');
        $this->assertSelectorTextContains('tbody', 'H3420 Side Mirror');
        $this->assertSelectorExists('form[action="/staff/orders"]');

        $client->request('GET', '/staff/orders', ['search' => '3', 'status' => 'Pending']);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('tbody', '#3');

        $client->request('GET', '/staff/orders/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('textarea[name="form[deliveryAddress]"]');
        $this->assertSelectorExists('select[name="form[items][0][productId]"]');
        $this->assertSelectorExists('input[name="form[items][0][quantity]"]');
        $this->assertSelectorExists('#add-order-item');
    }

    public function testStaffAppointmentFiltersUseBookingDateSourceAndStatus(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->find(6);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff/appointments', [
            'search' => 'Honda',
            'status' => 'Pending',
            'date' => '2026-11-17',
            'source' => 'online',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('tbody', 'Honda Click');
        $this->assertSelectorExists('select[name="status"]');
        $this->assertSelectorExists('textarea[name="rejectionReason"]');
    }

    public function testStaffPromotionHistorySupportsSearchAndStatusFilters(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->find(6);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff/promo-history', ['search' => 'oil', 'status' => 'Pending']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#claimSearch');
        $this->assertSelectorExists('#claimStatus');
        $this->assertSelectorTextContains('body', 'All claims:');
    }
}
