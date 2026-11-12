<?php

namespace App\Tests\Controller;

use App\Entity\ServiceBooking;
use App\Repository\ServiceBookingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookingControllerTest extends WebTestCase
{
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
}
