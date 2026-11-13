<?php

namespace App\Tests\Controller;

use App\Entity\CustomerOrder;
use App\Entity\Promo;
use App\Entity\PromoClaim;
use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\ServiceRecord;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\MotorcycleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StaffControllerTest extends WebTestCase
{
    public function testStaffDashboardRetainsLegacyOverviewAndRestrictsAdminPages(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->find(6);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('aside#staff-sidebar');
        $this->assertSelectorExists('a[aria-current="page"][href="/staff"]');
        $this->assertSelectorExists('[data-sidebar-toggle]');
        $this->assertSelectorTextContains('body', 'Total Products');
        $this->assertSelectorTextContains('body', 'Total Orders');
        $this->assertSelectorTextContains('body', 'Total Service Appointments');

        $client->request('GET', '/admin/users');
        $this->assertResponseStatusCodeSame(403);
    }

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
        $this->assertSelectorExists('[data-confirm-payment]');
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
        $this->assertSelectorExists('#panel-action-confirmation-modal');
        $this->assertSelectorTextContains('tbody', 'Honda Click');
        $this->assertSelectorExists('select[name="status"]');
        $this->assertSelectorExists('form[data-deferred-action="delete"]');
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

    public function testUnauthorizedAndCustomerAccessToStaffRoutesIsRestricted(): void
    {
        $client = static::createClient();

        // Anonymous user gets redirected to /login
        $client->request('GET', '/staff');
        $this->assertResponseRedirects('/login');

        // Customer role gets 403 Forbidden
        $customer = static::getContainer()->get(UserRepository::class)->find(1);
        self::assertNotNull($customer);
        $client->loginUser($customer);
        $client->request('GET', '/staff');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testStaffCannotModifyOrDeleteRecordsCreatedByOtherStaff(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepo = static::getContainer()->get(UserRepository::class);

        $staff1 = $userRepo->find(6);
        self::assertNotNull($staff1);

        $staff2 = $userRepo->findOneBy(['username' => 'staff_alt_test']);
        if (!$staff2) {
            $staff2 = new User();
            $staff2->setUsername('staff_alt_test');
            $staff2->setEmail('staff_alt_test@example.invalid');
            $staff2->setFirstName('Staff');
            $staff2->setLastName('Alt');
            $staff2->setRole('staff');
            $staff2->setPassword('dummy');
            $em->persist($staff2);
            $em->flush();
        }

        $customer = $userRepo->find(1);
        self::assertNotNull($customer);
        $service = $em->getRepository(Service::class)->findOneBy(['active' => true]);
        self::assertNotNull($service);

        // Record owned by staff1
        $record = new ServiceRecord(
            $customer,
            $service,
            $staff1,
            'Honda Beat',
            new \DateTimeImmutable(),
            'Owner check test',
        );
        $em->persist($record);

        // Appointment owned by staff1
        $booking = new ServiceBooking(
            $customer,
            $service,
            'Honda Beat',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $booking->setSource('staff');
        $booking->setCreatedByStaff($staff1);
        $em->persist($booking);

        // Promo claim owned by staff1
        $promo = $em->getRepository(Promo::class)->findOneBy(['active' => true]);
        self::assertNotNull($promo);
        $claim = new PromoClaim(
            $customer,
            'Test Customer',
            '09123456789',
            $promo->getSlug(),
            $promo->getItemOptions()[0] ?? 'Default Option',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $claim->setPromo($promo);
        $claim->setCreatedByStaff($staff1);
        $em->persist($claim);

        // Order owned by staff1
        $order = new CustomerOrder($customer, '123 Test St', 'Cash on Delivery', 'PENDING');
        $order->setCreatedByStaff($staff1);
        $em->persist($order);

        $em->flush();

        // Now login as staff2 (not the owner)
        $client->loginUser($staff2);

        // Attempting to edit or delete staff1's service record -> 403
        $client->request('GET', '/staff/service-records/'.$record->getId().'/edit');
        $this->assertResponseStatusCodeSame(403);
        $client->request('POST', '/staff/service-records/'.$record->getId().'/delete');
        $this->assertResponseStatusCodeSame(403);

        // Attempting to edit or delete staff1's appointment -> 403
        $client->request('GET', '/staff/appointments/'.$booking->getId().'/edit');
        $this->assertResponseStatusCodeSame(403);
        $client->request('POST', '/staff/appointments/'.$booking->getId().'/delete');
        $this->assertResponseStatusCodeSame(403);

        // Attempting to edit or delete staff1's promo claim -> 403
        $client->request('GET', '/staff/promo-claims/'.$claim->getId().'/edit');
        $this->assertResponseStatusCodeSame(403);
        $client->request('POST', '/staff/promo-claims/'.$claim->getId().'/delete');
        $this->assertResponseStatusCodeSame(403);

        // Attempting to delete staff1's order -> 403
        $client->request('POST', '/staff/orders/'.$order->getId().'/delete');
        $this->assertResponseStatusCodeSame(403);

        // Now login as staff1 (the owner) -> can access edit forms
        $client->loginUser($staff1);
        $client->request('GET', '/staff/service-records/'.$record->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/staff/appointments/'.$booking->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/staff/promo-claims/'.$claim->getId().'/edit');
        $this->assertResponseIsSuccessful();

        // Admin can access everything
        $admin = $userRepo->find(2);
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $client->request('GET', '/staff/service-records/'.$record->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/staff/appointments/'.$booking->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/staff/promo-claims/'.$claim->getId().'/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testStaffPanelTablesUseAdminTableStyle(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->find(6);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff/appointments');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.table-responsive');
        $this->assertSelectorExists('table.admin-table');

        $client->request('GET', '/staff/promo-history');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.table-responsive');
        $this->assertSelectorExists('table.admin-table');

        $client->request('GET', '/staff/service-records');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.table-responsive');
        $this->assertSelectorExists('table.admin-table');

        $client->request('GET', '/staff/orders');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.table-responsive');
        $this->assertSelectorExists('table.admin-table');
    }
}
