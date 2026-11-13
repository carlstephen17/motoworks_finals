<?php

namespace App\Tests\Controller;

use App\Entity\PromoClaim;
use App\Entity\CancellationUndo;
use App\Entity\User;
use App\Repository\PromoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testPromoClaimPersistsTheSelectedPromotionAndRemainsInCustomerHistoryAfterCancellation(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $customer = $container->get(UserRepository::class)->find(3);
        $otherCustomer = new User();
        $testIdentifier = uniqid('promo-test-');
        $otherCustomer
            ->setUsername($testIdentifier)
            ->setFirstName('Other')
            ->setLastName('Customer')
            ->setEmail($testIdentifier.'@example.test')
            ->setPassword('test-password')
            ->setRole('customer');
        $promos = $container->get(PromoRepository::class)->findActivePromos();
        self::assertNotNull($customer);
        self::assertNotNull($otherCustomer);
        self::assertGreaterThanOrEqual(2, count($promos));
        $promo = $promos[1];
        $connection = $entityManager->getConnection();
        $claimId = null;
        $connection->beginTransaction();

        try {
            $entityManager->persist($otherCustomer);
            $entityManager->flush();
            $client->loginUser($customer);
            $promosPage = $client->request('GET', '/promos');
            $this->assertResponseIsSuccessful();
            $claimForm = $promosPage->filter('#claimForm');
            self::assertSame(
                1,
                preg_match('/openClaimModal\('.preg_quote((string) $promo->getId(), '/').',/', (string) $client->getResponse()->getContent()),
                'Each Avail button must carry its own promotion ID.',
            );

            $client->request('POST', '/claim-promo', [
                'csrf_token' => $claimForm->filter('input[name="csrf_token"]')->attr('value'),
                'promo_id' => (string) $promo->getId(),
                'bundle_slug' => $promo->getSlug(),
                'fullname' => 'Promo Test Customer',
                'phone' => '09123456789',
                'selected_option' => $promo->getItemOptions()[0],
                'appointment_date' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
                'appointment_time' => '09:00',
            ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

            $this->assertResponseIsSuccessful();
            $result = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($result);
            self::assertTrue($result['success']);
            self::assertSame($promo->getId(), $result['promo_id']);

            $claimId = (int) $result['claim_id'];
            $claim = $entityManager->getRepository(PromoClaim::class)->find($claimId);
            self::assertInstanceOf(PromoClaim::class, $claim);
            self::assertSame($customer->getId(), $claim->getUser()?->getId());
            self::assertSame($promo->getId(), $claim->getPromo()?->getId());
            self::assertSame($promo->getSlug(), $claim->getBundleSlug());
            self::assertSame($promo->getItemOptions()[0], $claim->getSelectedOption());

            $historyPage = $client->request('GET', '/promo-history');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', $promo->getTitle());
            $this->assertSelectorExists('[data-promo-id="'.$promo->getId().'"]');
            $this->assertSelectorTextContains('body', 'PC-'.$claimId);

            $this->assertSelectorNotExists('#edit-claim-form');
            $client->xmlHttpRequest('POST', '/update-claim', [
                'csrf_token' => 'unused',
                'claim_id' => (string) $claimId,
                'record_type' => 'promo_claim',
                'selected_option' => $promo->getItemOptions()[0],
                'appointment_date' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d'),
                'appointment_time' => '10:30',
            ]);
            $this->assertResponseStatusCodeSame(403);
            self::assertSame('09:00', $entityManager->getRepository(PromoClaim::class)->find($claimId)?->getAppointmentTime()->format('H:i'));

            $historyPage = $client->request('GET', '/promo-history');
            $cancelForm = $historyPage->filter('form[action="/promo-history/promo_claim/'.$claimId.'/delete"]');
            $client->xmlHttpRequest('POST', '/promo-history/promo_claim/'.$claimId.'/delete', [
                '_token' => $cancelForm->filter('input[name="_token"]')->attr('value'),
            ]);
            $this->assertResponseIsSuccessful();
            $firstCancellation = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($firstCancellation);
            self::assertSame('Cancellation Pending', $entityManager->getRepository(PromoClaim::class)->find($claimId)?->getStatus());

            $client->xmlHttpRequest('POST', $firstCancellation['action']['undo_url'], [
                '_token' => $firstCancellation['action']['undo_token'],
            ]);
            $this->assertResponseIsSuccessful();
            self::assertSame('Pending', $entityManager->getRepository(PromoClaim::class)->find($claimId)?->getStatus());

            $historyPage = $client->request('GET', '/promo-history');
            $cancelForm = $historyPage->filter('form[action="/promo-history/promo_claim/'.$claimId.'/delete"]');
            $client->xmlHttpRequest('POST', '/promo-history/promo_claim/'.$claimId.'/delete', [
                '_token' => $cancelForm->filter('input[name="_token"]')->attr('value'),
            ]);
            $this->assertResponseIsSuccessful();
            $secondCancellation = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($secondCancellation);
            $connection->executeStatement(
                'UPDATE cancellation_undo SET undoExpiresAt = :expiresAt WHERE undoId = :id',
                [
                    'expiresAt' => (new \DateTimeImmutable('-1 second'))->format('Y-m-d H:i:s'),
                    'id' => $secondCancellation['action']['id'],
                ],
            );
            $entityManager->clear(CancellationUndo::class);
            $client->xmlHttpRequest('POST', $secondCancellation['action']['undo_url'], [
                '_token' => $secondCancellation['action']['undo_token'],
            ]);
            $this->assertResponseStatusCodeSame(409);
            self::assertSame('Cancelled', $entityManager->getRepository(PromoClaim::class)->find($claimId)?->getStatus());

            $historyPage = $client->request('GET', '/promo-history');
            $this->assertSelectorTextContains('body', 'PC-'.$claimId);
            $this->assertSelectorTextContains('body', 'Cancelled');

            $client->loginUser($otherCustomer);
            $client->request('GET', '/promo-history');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextNotContains('body', 'PC-'.$claimId);
            $this->assertSelectorTextNotContains('body', 'Promo Test Customer');
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }

        self::assertNull($entityManager->getRepository(PromoClaim::class)->find($claimId));
    }

    public function testClaimRejectsMismatchedPromotionIdAndBundleSlug(): void
    {
        $client = static::createClient();
        $customer = static::getContainer()->get(UserRepository::class)->find(3);
        $promos = static::getContainer()->get(PromoRepository::class)->findActivePromos();
        self::assertNotNull($customer);
        self::assertGreaterThanOrEqual(2, count($promos));

        $client->loginUser($customer);
        $page = $client->request('GET', '/promos');
        $client->request('POST', '/claim-promo', [
            'csrf_token' => $page->filter('#claimForm input[name="csrf_token"]')->attr('value'),
            'promo_id' => (string) $promos[0]->getId(),
            'bundle_slug' => $promos[1]->getSlug(),
            'fullname' => 'Promo Test Customer',
            'phone' => '09123456789',
            'selected_option' => $promos[0]->getItemOptions()[0],
            'appointment_date' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            'appointment_time' => '09:00',
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $this->assertResponseStatusCodeSame(422);
        self::assertFalse(json_decode((string) $client->getResponse()->getContent(), true)['success']);
    }
}
