<?php

namespace App\Controller;

use App\Entity\PromoClaim;
use App\Entity\ServiceBooking;
use App\Entity\User;
use App\Repository\PromoClaimRepository;
use App\Repository\PromoRepository;
use App\Repository\ServiceBookingRepository;
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

        $slug = trim((string) $request->request->get('bundle_slug', ''));
        $option = trim((string) $request->request->get('selected_option', ''));
        $name = trim((string) $request->request->get('fullname', $user->getFirstName().' '.$user->getLastName()));
        $phone = trim((string) $request->request->get('phone', $user->getPhone() ?? ''));
        $dateInput = (string) $request->request->get('appointment_date', '');
        $timeInput = (string) $request->request->get('appointment_time', '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
        $time = \DateTimeImmutable::createFromFormat('!H:i', $timeInput);
        $promo = $promos->findOneBy(['slug' => $slug, 'active' => true]);

        if (
            !$promo
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

        $entityManager->persist(new PromoClaim($user, $name, $phone, $slug, $option, $date, $time));
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Promotion successfully claimed.']);
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
            'canEdit' => $user->getRole() === 'customer' && strtolower(trim($record['status'])) !== 'completed',
        ]);
    }

    #[Route('/promo-history/{type}/{id}/delete', name: 'promo_claim_delete', requirements: ['type' => 'service_booking|promo_claim', 'id' => '\d+'], methods: ['POST'])]
    public function delete(
        string $type,
        int $id,
        Request $request,
        ServiceBookingRepository $bookings,
        PromoClaimRepository $promoClaims,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('promo_history_delete_'.$type.'_'.$id, (string) $request->request->get('_token'))) {
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

        $entityManager->remove($record);
        $entityManager->flush();
        $this->addFlash('success', 'Promotion claim cancelled.');

        return $this->redirectToRoute('promo_history');
    }

    #[Route('/update-claim', name: 'update_claim', methods: ['POST'])]
    public function update(
        Request $request,
        ServiceBookingRepository $bookings,
        PromoClaimRepository $promoClaims,
        PromoRepository $promos,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            return $this->respond($request, ['success' => false, 'message' => 'You are not allowed to update this claim.'], 403);
        }
        if (!$this->isCsrfTokenValid('update_claim', (string) $request->request->get('csrf_token'))) {
            return $this->respond($request, ['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $id = filter_var($request->request->get('claim_id'), FILTER_VALIDATE_INT);
        $type = (string) $request->request->get('record_type', 'service_booking');
        $record = match ($type) {
            'service_booking' => $id ? $bookings->find($id) : null,
            'promo_claim' => $id ? $promoClaims->find($id) : null,
            default => null,
        };
        if (
            (!$record instanceof ServiceBooking && !$record instanceof PromoClaim)
            || $record->getUser() !== $user
            || ($record instanceof ServiceBooking && $record->getBundleSlug() === null)
        ) {
            return $this->respond($request, ['success' => false, 'message' => 'Claim not found or you do not have permission to edit it.'], 404);
        }
        if (strtolower(trim((string) $record->getStatus())) === 'completed') {
            return $this->respond($request, ['success' => false, 'message' => 'Completed promotion claims cannot be edited.'], 422);
        }

        $promo = $promos->findOneBy(['slug' => $record->getBundleSlug(), 'active' => true]);
        $option = trim((string) $request->request->get('selected_option', ''));
        $dateInput = (string) $request->request->get('appointment_date', '');
        $timeInput = (string) $request->request->get('appointment_time', '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);
        $time = \DateTimeImmutable::createFromFormat('!H:i', $timeInput);

        if (
            !$promo
            || !in_array($option, $promo->getItemOptions(), true)
            || !$date
            || $date->format('Y-m-d') !== $dateInput
            || $date < new \DateTimeImmutable('today')
            || !$time
            || $time->format('H:i') !== $timeInput
        ) {
            return $this->respond($request, ['success' => false, 'message' => 'Enter valid appointment details and a current promotion option.'], 422);
        }

        if ($record instanceof ServiceBooking) {
            $record->setBundleOption($option);
            $record->setBookingDate($date);
            $record->setBookingTime($time);
        } else {
            $record->setSelectedOption($option);
            $record->setAppointmentDate($date);
            $record->setAppointmentTime($time);
        }
        $entityManager->flush();

        return $this->respond($request, ['success' => true, 'message' => 'Promotion appointment updated.']);
    }

    private function respond(Request $request, array $payload, int $status = 200): Response
    {
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json($payload, $status);
        }

        $this->addFlash($payload['success'] ? 'success' : 'error', $payload['message']);

        return $this->redirectToRoute('promo_history');
    }
}
