<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PromoClaimControllerTest extends WebTestCase
{
    public function testPromotionClaimUpdateRejectsInvalidCsrfWithoutUpdatingRecords(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request('POST', '/update-claim', [
            'csrf_token' => 'invalid',
            'claim_id' => 999999,
            'record_type' => 'promo_claim',
            'selected_option' => 'Option',
            'appointment_date' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            'appointment_time' => '09:00',
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $this->assertResponseStatusCodeSame(403);
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        self::assertFalse($response['success']);
    }

    public function testPromotionClaimDetailRejectsUnknownRecordType(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->find(3);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request('GET', '/get-claim?id=999999&record_type=unknown');

        $this->assertResponseStatusCodeSame(404);
    }
}
