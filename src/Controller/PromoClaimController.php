<?php

namespace App\Controller;

use App\Entity\PromoClaim;
use App\Entity\ServiceBooking;
use App\Entity\User;
use App\Repository\PromoClaimRepository;
use App\Repository\PromoRepository;
use App\Repository\ServiceBookingRepository;
use App\Service\CancellationActionPresenter;
use App\Service\CancellationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PromoClaimController extends AbstractController
{
    #[Route('/claim-promo', name: 'claim_promo', methods: ['POST'])]
    public function claim(
        Request $request,
        PromoRepository $promos,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            return $this->json(['success' => false, 'message' => 'Sign in with a customer account to claim a promotion.'], 401);
        }
        if (!$this->isCsrfTokenValid('claim_promo', (string) $request->request->get('csrf_token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
        }

        $promoId = filter_var($request->request->get('promo_id'), FILTER_VALIDATE_INT);
        $slug = trim((string) $request->request->get('bundle_slug', ''));
        $option = trim((string) $request->request->get('selected_option', ''));
        $name = trim((string) $request->request->get('fullname', $user->getFirstName().' '.$user->getLastName()));
        $phone = trim((string) $request->request->get('phone', $user->getPhone() ?? ''));
        $dateInput = (string) $request->request->get('appointment_date', '');
        $timeInput = (string) $request->request->get('appointment_time', '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
        $time = \DateTimeImmutable::createFromFormat('!H:i', $timeInput);
        $promo = $promoId ? $promos->findOneBy(['id' => $promoId, 'active' => true]) : null;

        if (
            !$promo
            || $promo->getSlug() !== $slug
            || $name === ''
            || mb_strlen($name) > 255
            || !preg_match('/^[0-9+(). -]{7,20}$/', $phone)
            || !in_array($option, $promo->getItemOptions(), true)
            || !$date
            || $date->format('Y-m-d') !== $dateInput
            || $date < new \DateTimeImmutable('today')
            || !$time
            || $time->format('H:i') !== $timeInput
        ) {
            return $this->json(['success' => false, 'message' => 'Please provide valid promotion and appointment details.'], 422);
        }

        $claim = new PromoClaim($user, $name, $phone, $slug, $option, $date, $time);
        $claim->setPromo($promo);
        $entityManager->persist($claim);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Promotion successfully claimed.',
            'claim_id' => $claim->getId(),
            'promo_id' => $promo->getId(),
            'promo_title' => $promo->getTitle(),
            'bundle_slug' => $promo->getSlug(),
            'selected_option' => $option,
            'customer_id' => $user->getId(),
        ]);
    }

    #[Route('/get-claim', name: 'get_claim', methods: ['GET'])]
    public function show(
        Request $request,
        ServiceBookingRepository $bookings,
        PromoClaimRepository $promoClaims,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $id = filter_var($request->query->get('id'), FILTER_VALIDATE_INT);
        $type = (string) $request->query->get('record_type', 'service_booking');
        $isAdmin = $user->getRole() === 'admin';
        if ($type === 'service_booking') {
            $booking = $id ? $bookings->find($id) : null;
            if (!$booking instanceof ServiceBooking || $booking->getBundleSlug() === null || ($booking->getUser() !== $user && !$isAdmin)) {
                throw $this->createNotFoundException('Promotion claim not found.');
            }

            $record = [
                'id' => $booking->getId(),
                'type' => $type,
                'bundleSlug' => $booking->getBundleSlug(),
                'selectedOption' => $booking->getBundleOption(),
                'appointmentDate' => $booking->getBookingDate(),
                'appointmentTime' => $booking->getBookingTime(),
                'fullName' => trim($booking->getUser()->getFirstName().' '.$booking->getUser()->getLastName()),
                'phone' => $booking->getUser()->getPhone(),
                'vehicleModel' => $booking->getVehicleModel(),
                'status' => $booking->getStatus() ?? 'Pending',
            ];
        } elseif ($type === 'promo_claim') {
            $claim = $id ? $promoClaims->find($id) : null;
            if (!$claim instanceof PromoClaim || ($claim->getUser() !== $user && !$isAdmin)) {
                throw $this->createNotFoundException('Promotion claim not found.');
            }

            $record = [
                'id' => $claim->getId(),
                'type' => $type,
                'bundleSlug' => $claim->getBundleSlug(),
                'promoTitle' => $claim->getPromo()?->getTitle() ?? $claim->getBundleSlug(),
                'promoId' => $claim->getPromo()?->getId(),
                'selectedOption' => $claim->getSelectedOption(),
                'appointmentDate' => $claim->getAppointmentDate(),
                'appointmentTime' => $claim->getAppointmentTime(),
                'fullName' => $claim->getFullName(),
                'phone' => $claim->getPhone(),
                'vehicleModel' => null,
                'status' => $claim->getStatus() ?? 'Pending',
            ];
        } else {
            throw $this->createNotFoundException('Promotion claim not found.');
        }

        return $this->render('booking/claim_detail.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/promo-history/{type}/{id}/delete', name: 'promo_claim_delete', requirements: ['type' => 'service_booking|promo_claim', 'id' => '\d+'], methods: ['POST'])]
    public function delete(
        string $type,
        int $id,
        Request $request,
        ServiceBookingRepository $bookings,
        PromoClaimRepository $promoClaims,
        CancellationManager $cancellations,
        CancellationActionPresenter $actionPresenter,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('promo_history_delete_'.$type.'_'.$id, (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
            }
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $record = match ($type) {
            'service_booking' => $bookings->find($id),
            'promo_claim' => $promoClaims->find($id),
            default => null,
        };
        if (
            (!$record instanceof ServiceBooking && !$record instanceof PromoClaim)
            || $record->getUser() !== $user
            || ($record instanceof ServiceBooking && $record->getBundleSlug() === null)
        ) {
            throw $this->createNotFoundException('Promotion claim not found.');
        }

        try {
            $action = $record instanceof ServiceBooking
                ? $cancellations->beginAppointmentCancellation($record, $user)
                : $cancellations->beginPromoClaimCancellation($record, $user);
            $payload = [
                'success' => true,
                'message' => 'Promotion cancellation pending.',
                'action' => $actionPresenter->present($action),
            ];
        } catch (\DomainException $exception) {
            $payload = ['success' => false, 'message' => $exception->getMessage()];
        }

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json($payload, $payload['success'] ? 200 : 409);
        }

        $this->addFlash($payload['success'] ? 'success' : 'error', $payload['message']);

        return $this->redirectToRoute('promo_history');
    }

    #[Route('/update-claim', name: 'update_claim', methods: ['POST'])]
    public function update(Request $request): Response
    {
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json([
                'success' => false,
                'message' => 'Customers may view or cancel eligible promotion transactions, but cannot edit promo history.',
            ], 403);
        }

        throw $this->createAccessDeniedException('Customers may view or cancel eligible promotion transactions, but cannot edit promo history.');
    }
}
