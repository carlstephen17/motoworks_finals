<?php

namespace App\Tests\Controller;

use App\Entity\ServiceBooking;
use App\Repository\PromoRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookingControllerTest extends WebTestCase
{
    public function testServicesPageIncludesBookingModalForGuestFlow(): void
    {
        $client = static::createClient();
        $client->request('GET', '/services');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#login-modal');
        $this->assertSelectorExists('#service-booking-modal');
        $this->assertSelectorExists('#ajax-service-booking-form');
    }

    public function testCustomerCanViewHistoryAndPendingBookingEditModal(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $pendingBooking = static::getContainer()
            ->get(ServiceBookingRepository::class)
            ->findOneBy(['user' => $user, 'status' => 'Pending']);

        $client->loginUser($user);
        $client->request('GET', '/appointment-history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.appointment-history-container');
        if ($pendingBooking instanceof ServiceBooking) {
            $this->assertSelectorExists('#editModal'.$pendingBooking->getId());

            $client->request('GET', '/appointments/'.$pendingBooking->getId().'/edit');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#editModal'.$pendingBooking->getId().' form');
        }
    }

    public function testCustomerCanOpenBookingFormWithSelectedService(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request('GET', '/book-service?service_id=1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('select[name="form[serviceId]"] option[value="1"][selected]');
    }

    public function testCustomerPromotionHistoryRendersTheMixedClaimActions(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request('GET', '/promo-history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.styled-table');
        $this->assertSelectorExists('#view-claim-modal');
        $this->assertSelectorExists('#edit-claim-form');
    }

    public function testInvalidAjaxBookingReturnsValidationErrorWithoutCreatingBooking(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->xmlHttpRequest('POST', '/book-service', ['form' => []]);

        $this->assertResponseStatusCodeSame(422);
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        self::assertFalse($response['success']);
    }

    public function testInvalidAppointmentEditKeepsTheEditFormOpen(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);
        $pendingBooking = static::getContainer()
            ->get(ServiceBookingRepository::class)
            ->findOneBy(['user' => $user, 'status' => 'Pending']);

        if (!$pendingBooking instanceof ServiceBooking) {
            self::markTestSkipped('No pending appointment is available for this read-only form test.');
        }

        $id = $pendingBooking->getId();
        $client->loginUser($user);
        $client->request('POST', '/appointments/'.$id.'/edit', [
            'appointment_'.$id => ['_token' => 'invalid'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#editModal'.$id.' form');
    }

    public function testCustomerPromoBookingIsSavedAndAppearsInBothHistories(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $customer = $container->get(UserRepository::class)->find(3);
        $service = $container->get(ServiceRepository::class)->findActiveServices()[0] ?? null;
        $promo = $container->get(PromoRepository::class)->findActivePromos()[0] ?? null;
        self::assertNotNull($customer);
        self::assertNotNull($service);
        self::assertNotNull($promo);
        self::assertNotSame([], $promo->getItemOptions());

        $client->loginUser($customer);
        $promosPage = $client->request('GET', '/promos');
        $token = $promosPage->filter('#claimForm input[name="form[_token]"]')->attr('value');
        self::assertNotNull($token);
        $connection = $entityManager->getConnection();
        $bookingId = null;
        $bookingDate = new \DateTimeImmutable('tomorrow');
        $connection->beginTransaction();

        try {
            $client->xmlHttpRequest('POST', '/book-service', [
                'form' => [
                    '_token' => $token,
                    'serviceId' => (string) $service->getId(),
                    'vehicleModel' => 'Temporary verification motorcycle',
                    'bookingDate' => $bookingDate->format('Y-m-d'),
                    'bookingTime' => '09:00',
                    'promoSlug' => $promo->getSlug(),
                    'promoOption' => $promo->getItemOptions()[0],
                    'notes' => 'Temporary verification booking',
                ],
            ]);

            $this->assertResponseIsSuccessful();
            $payload = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($payload);
            self::assertTrue($payload['success']);
            $bookingId = (int) $payload['booking_id'];

            $booking = $entityManager->getRepository(ServiceBooking::class)->find($bookingId);
            self::assertInstanceOf(ServiceBooking::class, $booking);
            self::assertSame($customer->getId(), $booking->getUser()->getId());
            self::assertSame($service->getId(), $booking->getService()->getId());
            self::assertSame($promo->getSlug(), $booking->getBundleSlug());
            self::assertSame($promo->getItemOptions()[0], $booking->getBundleOption());

            $client->request('GET', '/appointment-history');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', $service->getName());

            $client->request('GET', '/promo-history');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', $promo->getSlug());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }

        self::assertNull($entityManager->getRepository(ServiceBooking::class)->find($bookingId));
    }
}
