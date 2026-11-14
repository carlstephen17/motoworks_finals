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
        $staff = static::getContainer()->get(UserRepository::class)->findOneBy(['role' => 'staff']);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('aside#staff-sidebar');
        $this->assertSelectorExists('a[aria-current="page"][href="/staff"]');
        $this->assertSelectorExists('#staff-sidebar a[href="/"]');
        $this->assertSelectorTextContains('#staff-sidebar a[href="/"]', 'Website');
        $this->assertSelectorExists('[data-sidebar-toggle]');
        $this->assertSelectorTextContains('body', 'Total Products');
        $this->assertSelectorTextContains('body', 'Total Orders');
        $this->assertSelectorTextContains('body', 'Total Service Appointments');
        $this->assertSelectorNotExists('a[href="/staff/service-records"]');
        $this->assertSelectorNotExists('[data-dashboard-count="serviceRecords"]');
        self::assertStringNotContainsString('Total Service Records', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/users');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testStaffWalkInFormsSupportRegisteredAndNewMotorcycles(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->findOneBy(['role' => 'staff']);
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
        $staff = static::getContainer()->get(UserRepository::class)->findOneBy(['role' => 'staff']);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff/orders');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('tbody', 'Motorcycle Helmet');
        $this->assertSelectorTextContains('tbody', 'H3420 Side Mirror');
        $this->assertSelectorExists('[data-confirm-payment]');
        $this->assertSelectorExists('form[action="/staff/orders"]');
        $this->assertSelectorExists('.order-filters .order-create-button[href="/staff/orders/new"]');
        $this->assertSelectorExists('td.table-row-actions button[data-open-order-details]');
        $this->assertSelectorExists('td.table-row-actions button[data-edit-order-details]');
        $this->assertSelectorNotExists('td.table-row-actions form');
        $statusOptionSets = $client->getCrawler()->filter('.order-status-form select[data-order-status]')->each(
            static fn ($select): array => $select->filter('option')->each(static fn ($option): ?string => $option->attr('value')),
        );
        self::assertNotEmpty($statusOptionSets);
        foreach ($statusOptionSets as $options) {
            self::assertSame(['Pending', 'Completed', 'Cancelled'], $options);
        }

        $client->request('GET', '/staff/orders', ['search' => '3', 'status' => 'Pending']);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('tbody', '#3');

        $client->request('GET', '/staff/orders/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('textarea[name="order_create[deliveryAddress]"]');
        $this->assertSelectorExists('select[name="order_create[items][0][productId]"]');
        $this->assertSelectorExists('input[name="order_create[items][0][quantity]"]');
        $this->assertSelectorExists('#add-order-item');
    }

    public function testStaffAppointmentFiltersUseBookingDateSourceAndStatus(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $staff = $container->get(UserRepository::class)->findOneBy(['role' => 'staff']);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $customer = $container->get(UserRepository::class)->find(1);
            $service = $entityManager->getRepository(Service::class)->findOneBy(['active' => true]);
            self::assertNotNull($customer);
            self::assertNotNull($service);

            $booking = new ServiceBooking(
                $customer,
                $service,
                'Honda Click',
                new \DateTimeImmutable('2026-11-17'),
                new \DateTimeImmutable('2026-11-17 10:00:00'),
                null,
                null,
                null,
            );
            $booking->setSource('customer_online');
            $entityManager->persist($booking);
            $entityManager->flush();

            $client->request('GET', '/staff/appointments', [
                'search' => 'Honda',
                'status' => 'Pending',
                'date' => '2026-11-17',
                'source' => 'online',
            ]);

            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#panel-action-confirmation-modal');
            $this->assertSelectorTextContains('tbody', 'Honda Click');
            $this->assertSelectorExists('button.view-appointment');
            $this->assertSelectorExists('button.edit-appointment');
            $this->assertSelectorExists('#staffAppointmentDetails');
            $this->assertSelectorExists('#staffAppointmentEdit');
            $this->assertSelectorExists('#appointmentFilters[data-auto-filter]');
            $this->assertSelectorNotExists('#appointmentFilters button[type="submit"]');
            $this->assertSelectorNotExists('#appointmentSource');
            $this->assertSelectorNotExists('#appointmentFilters a.btn-secondary');
            self::assertStringNotContainsString('Source', $client->getCrawler()->filter('#appointmentTable thead')->text());
            $this->assertSelectorExists('#staffAppointmentDetails .order-details-modal-header');
            $this->assertSelectorCount(3, '#staffAppointmentDetails .order-detail-section');
            self::assertSame(
                ['Pending', 'Completed', 'Cancelled'],
                $client->getCrawler()->filter('#staffAppointmentEdit select[data-appointment-field="status"] option')->each(static fn ($option): ?string => $option->attr('value')),
            );
            $this->assertSelectorNotExists('.table-row-actions form');
            $this->assertSelectorNotExists('.table-row-actions select');
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    public function testStaffPromotionHistorySupportsSearchAndStatusFilters(): void
    {
        $client = static::createClient();
        $staff = static::getContainer()->get(UserRepository::class)->findOneBy(['role' => 'staff']);
        self::assertNotNull($staff);
        $client->loginUser($staff);

        $client->request('GET', '/staff/promo-history', ['search' => 'oil', 'status' => 'Pending']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#claimSearch');
        $this->assertSelectorExists('#claimStatus');
        $this->assertSelectorTextContains('body', 'All claims:');
        $this->assertSelectorExists('button.view-promo-claim');
        $this->assertSelectorExists('button.edit-promo-claim');
        $this->assertSelectorExists('#staffPromoClaimDetails');
        $this->assertSelectorExists('#staffPromoClaimEdit');
        $this->assertSelectorExists('#promoClaimFilters[data-auto-filter]');
        $this->assertSelectorNotExists('#promoClaimFilters button[type="submit"]');
        $this->assertSelectorNotExists('#promoClaimFilters a.btn-secondary');
        $this->assertSelectorExists('#staffPromoClaimDetails .order-details-modal-header');
        $this->assertSelectorCount(2, '#staffPromoClaimDetails .order-detail-section');
        self::assertSame(
            ['Pending', 'Completed', 'Cancelled'],
            $client->getCrawler()->filter('#staffPromoClaimEdit select[data-promo-field="status"] option')->each(static fn ($option): ?string => $option->attr('value')),
        );
        $this->assertSelectorNotExists('.table-row-actions form');
        $this->assertSelectorNotExists('.table-row-actions select');
    }

    public function testStaffEditModalsPersistAppointmentOrderAndPromotionChanges(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $staff = $container->get(UserRepository::class)->findOneBy(['role' => 'staff']);
        $customer = $container->get(UserRepository::class)->find(1);
        $service = $entityManager->getRepository(Service::class)->findOneBy(['active' => true]);
        $promo = $entityManager->getRepository(Promo::class)->findOneBy(['active' => true]);
        self::assertNotNull($staff);
        self::assertNotNull($customer);
        self::assertNotNull($service);
        self::assertNotNull($promo);
        self::assertNotEmpty($promo->getItemOptions());

        $client->loginUser($staff);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $appointment = null;
        $claim = null;
        $order = null;
        try {
            $appointment = new ServiceBooking(
                $customer,
                $service,
                'Honda Click',
                new \DateTimeImmutable('2026-11-18'),
                new \DateTimeImmutable('2026-11-18 10:00'),
                null,
                null,
                null,
            );
            $appointment->setSource('staff');
            $appointment->setCreatedByStaff($staff);
            $claim = new PromoClaim(
                $customer,
                $customer->getFirstName().' '.$customer->getLastName(),
                $customer->getPhone() ?? '',
                $promo->getSlug(),
                $promo->getItemOptions()[0],
                new \DateTimeImmutable('2026-11-18'),
                new \DateTimeImmutable('2026-11-18 11:00'),
            );
            $claim->setPromo($promo);
            $claim->setCreatedByStaff($staff);
            $order = new CustomerOrder($customer, '123 Test Street', 'Cash on Delivery', 'TEST-STAFF-EDIT');
            $order->setCreatedByStaff($staff);
            $entityManager->persist($appointment);
            $entityManager->persist($claim);
            $entityManager->persist($order);
            $entityManager->flush();
            $connection->commit();

            $appointmentId = $appointment->getId();
            $client->request('GET', '/staff/appointments');
            $appointmentData = json_decode(
                (string) $client->getCrawler()->filter('tr[data-id="'.$appointmentId.'"] .edit-appointment')->attr('data-appointment'),
                true,
            );
            $appointmentEditData = [
                    'service' => (string) $service->getId(),
                    'vehicleModel' => 'Honda Click Updated',
                    'bookingDate' => '2026-11-19',
                    'bookingTime' => '12:30',
                    'status' => 'Confirmed',
                    'notes' => 'Updated by staff',
                    'rejectionReason' => '',
                ];
            $client->xmlHttpRequest('POST', '/staff/appointments/'.$appointmentId.'/edit', [
                '_token' => $appointmentData['csrfToken'],
                'appointment_edit' => $appointmentEditData,
            ]);
            $this->assertResponseStatusCodeSame(422);
            $appointmentEditData['status'] = 'Completed';
            $client->xmlHttpRequest('POST', '/staff/appointments/'.$appointmentId.'/edit', [
                '_token' => $appointmentData['csrfToken'],
                'appointment_edit' => $appointmentEditData,
            ]);
            $this->assertResponseIsSuccessful();
            $appointmentResult = json_decode((string) $client->getResponse()->getContent(), true)['appointment'];
            self::assertSame('Completed', $appointmentResult['status']);
            self::assertSame('Honda Click Updated', $appointmentResult['edit']['vehicleModel']);
            $entityManager->clear();
            $appointment = $entityManager->find(ServiceBooking::class, $appointmentId);
            self::assertSame('Honda Click Updated', $appointment?->getVehicleModel());

            $claimId = $claim->getId();
            $client->request('GET', '/staff/promo-history');
            $claimData = json_decode(
                (string) $client->getCrawler()->filter('tr[data-id="'.$claimId.'"] .edit-promo-claim')->attr('data-claim'),
                true,
            );
            $claimEditData = [
                    'bundleSlug' => $promo->getSlug(),
                    'selectedOption' => $promo->getItemOptions()[0],
                    'appointmentDate' => '2026-11-20',
                    'appointmentTime' => '13:00',
                    'status' => 'Used',
                ];
            $client->xmlHttpRequest('POST', '/staff/promo-claims/'.$claimId.'/edit', [
                '_token' => $claimData['csrfToken'],
                'promo_claim_edit' => $claimEditData,
            ]);
            $this->assertResponseStatusCodeSame(422);
            $claimEditData['status'] = 'Completed';
            $client->xmlHttpRequest('POST', '/staff/promo-claims/'.$claimId.'/edit', [
                '_token' => $claimData['csrfToken'],
                'promo_claim_edit' => $claimEditData,
            ]);
            $this->assertResponseIsSuccessful();
            $claimResult = json_decode((string) $client->getResponse()->getContent(), true)['claim'];
            self::assertSame('Completed', $claimResult['status']);
            $entityManager->clear();
            $claim = $entityManager->find(PromoClaim::class, $claimId);
            self::assertSame('Completed', $claim?->getStatus());

            $orderId = $order->getId();
            $client->request('GET', '/staff/orders');
            $orderToken = $client->getCrawler()->filter('form.order-status-form[data-order-id="'.$orderId.'"] input[name="_token"]')->attr('value');
            $client->xmlHttpRequest('POST', '/staff/orders/'.$orderId.'/status', [
                '_token' => $orderToken,
                'status' => 'Completed',
            ]);
            $this->assertResponseIsSuccessful();
            self::assertSame('Completed', json_decode((string) $client->getResponse()->getContent(), true)['status']);
            $entityManager->clear();
            $order = $entityManager->find(CustomerOrder::class, $orderId);
            self::assertSame('Completed', $order?->getStatus());

            $client->xmlHttpRequest('POST', '/staff/orders/'.$orderId.'/status', [
                '_token' => $orderToken,
                'status' => 'Processing',
            ]);
            $this->assertResponseStatusCodeSame(422);
        } finally {
            $entityManager->clear();
            foreach ([
                [ServiceBooking::class, $appointment?->getId()],
                [PromoClaim::class, $claim?->getId()],
                [CustomerOrder::class, $order?->getId()],
            ] as [$class, $id]) {
                if ($id !== null) {
                    $entity = $entityManager->find($class, $id);
                    if ($entity !== null) {
                        $entityManager->remove($entity);
                    }
                }
            }
            $entityManager->flush();
            $entityManager->clear();
        }
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

        $staff1 = $userRepo->findOneBy(['role' => 'staff']);
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
            null,
            null,
            null,
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
        $staff = static::getContainer()->get(UserRepository::class)->findOneBy(['role' => 'staff']);
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
