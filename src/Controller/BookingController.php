<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\User;
use App\Repository\PromoClaimRepository;
use App\Repository\PromoRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\ServiceRepository;
use App\Service\CancellationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;

class BookingController extends AbstractController
{
    #[Route('/book-service', name: 'booking_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ServiceRepository $services,
        PromoRepository $promos,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $availableServices = $services->findActiveServices();
        $serviceChoices = [];
        foreach ($availableServices as $service) {
            $serviceChoices[$service->getName()] = $service->getId();
        }

        $availablePromos = $promos->findActivePromos();
        $promoChoices = ['No promotion' => ''];
        foreach ($availablePromos as $promo) {
            $promoChoices[$promo->getTitle()] = $promo->getSlug();
        }

        $requestedServiceId = $request->query->getInt('service_id');
        $selectedServiceId = in_array($requestedServiceId, $serviceChoices, true)
            ? $requestedServiceId
            : null;
        $formData = $selectedServiceId === null ? [] : ['serviceId' => $selectedServiceId];

        $form = $this->createFormBuilder($formData, ['csrf_protection' => true])
            ->add('serviceId', ChoiceType::class, [
                'label' => 'Service',
                'choices' => $serviceChoices,
                'placeholder' => 'Choose a service',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('vehicleModel', TextType::class, [
                'label' => 'Motorcycle model',
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('bookingDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control', 'min' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
            ])
            ->add('bookingTime', TimeType::class, [
                'label' => 'Time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('promoSlug', ChoiceType::class, [
                'label' => 'Promotion (optional)',
                'choices' => $promoChoices,
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('promoOption', TextType::class, [
                'label' => 'Promotion option (optional)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 255],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => [new Length(max: 2000)],
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $service = $services->find((int) $data['serviceId']);
            $date = $data['bookingDate'];
            $time = $data['bookingTime'];
            $vehicleModel = trim((string) $data['vehicleModel']);
            $promoSlug = trim((string) ($data['promoSlug'] ?? ''));
            $promoOption = trim((string) ($data['promoOption'] ?? ''));
            $promo = $promoSlug !== '' ? $promos->findOneBy(['slug' => $promoSlug, 'active' => true]) : null;

            if (!$service || !$service->isActive()) {
                $this->addFlash('error', 'The selected service is no longer available.');
            } elseif ($vehicleModel === '' || mb_strlen($vehicleModel) > 255) {
                $this->addFlash('error', 'Enter a valid motorcycle model.');
            } elseif (!$date instanceof \DateTimeImmutable || $date->setTime(0, 0) < new \DateTimeImmutable('today')) {
                $this->addFlash('error', 'Choose a booking date that is today or later.');
            } elseif ($promoSlug !== '' && (!$promo || !in_array($promoOption, $promo->getItemOptions(), true))) {
                $this->addFlash('error', 'Choose an available promotion option.');
            } else {
                $booking = new ServiceBooking(
                    $user,
                    $service,
                    $vehicleModel,
                    $date,
                    $time,
                    $promoSlug !== '' ? $promoSlug : null,
                    $promoSlug !== '' ? $promoOption : null,
                    trim((string) ($data['notes'] ?? '')) ?: null,
                );
                $booking->setPromo($promo);
                $entityManager->persist($booking);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Your service booking has been submitted.',
                        'booking_id' => $booking->getId(),
                        'booking_reference' => (string) $booking->getId(),
                        'customer_name' => $user->getFirstName() . ' ' . $user->getLastName(),
                        'service_name' => $service->getName(),
                        'vehicle' => $vehicleModel,
                        'booking_date' => $date->format('Y-m-d'),
                        'booking_time' => $time->format('H:i:s'),
                        'phone' => $user->getPhone() ?? '',
                        'notes' => trim((string) ($data['notes'] ?? '')),
                    ]);
                }

                $this->addFlash('success', 'Your service booking has been submitted.');

                return $this->redirectToRoute('appointment_history');
            }
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
            $messages = [];
            foreach ($form->getErrors(true) as $error) {
                $messages[] = $error->getMessage();
            }
            $messages = array_merge($messages, $request->getSession()->getFlashBag()->get('error'));

            return $this->json([
                'success' => false,
                'message' => $messages ? implode(' ', array_unique($messages)) : 'Please check the booking details and try again.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('booking/new.html.twig', ['form' => $form]);
    }

    #[Route('/appointment-history', name: 'appointment_history', methods: ['GET'])]
    public function history(ServiceBookingRepository $bookings, CancellationManager $cancellations): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $pendingCancellations = $cancellations->findPendingByRecordForUser($user);

        return $this->renderAppointmentHistory($user, $bookings, $pendingCancellations);
    }

    #[Route('/appointments/{id}/edit', name: 'appointment_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }

        throw $this->createAccessDeniedException('Customers may view or cancel eligible appointments, but cannot edit appointment history.');
    }

    #[Route('/promo-history', name: 'promo_history', methods: ['GET'])]
    public function promoHistory(
        ServiceBookingRepository $bookings,
        PromoClaimRepository $promoClaims,
        CancellationManager $cancellations,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if ($user->getRole() === 'admin') {
            return $this->redirectToRoute('admin_promo_history');
        }
        if ($user->getRole() === 'staff') {
            return $this->redirectToRoute('staff_promo_history');
        }
        if ($user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }

        $pendingCancellations = $cancellations->findPendingByRecordForUser($user);
        $history = [];
        foreach ($bookings->findForUser($user, true) as $booking) {
            $history[] = [
                'type' => 'service_booking',
                'id' => $booking->getId(),
                'bundleSlug' => $booking->getBundleSlug(),
                'promoTitle' => $booking->getPromo()?->getTitle() ?? $booking->getBundleSlug(),
                'promoId' => $booking->getPromo()?->getId(),
                'selectedOption' => $booking->getBundleOption(),
                'appointmentDate' => $booking->getBookingDate(),
                'appointmentTime' => $booking->getBookingTime(),
                'createdAt' => $booking->getCreatedAt(),
                'serviceName' => $booking->getService()->getName(),
                'fullName' => trim($user->getFirstName().' '.$user->getLastName()),
                'phone' => $user->getPhone(),
                'vehicleModel' => $booking->getVehicleModel(),
                'status' => $booking->getStatus() ?? 'Pending',
                'paymentStatus' => null,
            ];
        }
        foreach ($promoClaims->findForUser($user) as $claim) {
            $history[] = [
                'type' => 'promo_claim',
                'id' => $claim->getId(),
                'bundleSlug' => $claim->getBundleSlug(),
                'promoTitle' => $claim->getPromo()?->getTitle() ?? $claim->getBundleSlug(),
                'promoId' => $claim->getPromo()?->getId(),
                'selectedOption' => $claim->getSelectedOption(),
                'appointmentDate' => $claim->getAppointmentDate(),
                'appointmentTime' => $claim->getAppointmentTime(),
                'createdAt' => $claim->getCreatedAt(),
                'serviceName' => null,
                'fullName' => $claim->getFullName(),
                'phone' => $claim->getPhone(),
                'vehicleModel' => null,
                'status' => $claim->getStatus() ?? 'Pending',
                'paymentStatus' => null,
            ];
        }
        usort($history, static function (array $first, array $second): int {
            $dateComparison = $first['appointmentDate']->format('Y-m-d') <=> $second['appointmentDate']->format('Y-m-d');

            return $dateComparison !== 0
                ? $dateComparison
                : $first['appointmentTime']->format('H:i:s') <=> $second['appointmentTime']->format('H:i:s');
        });

        return $this->render('booking/promo_history.html.twig', [
            'claims' => $history,
            'pendingCancellations' => $pendingCancellations,
        ]);
    }

    private function renderAppointmentHistory(
        User $user,
        ServiceBookingRepository $bookings,
        array $pendingCancellations,
    ): Response {
        $appointments = $bookings->findForUser($user);

        return $this->render('booking/history.html.twig', [
            'bookings' => $appointments,
            'pendingCancellations' => $pendingCancellations,
        ]);
    }
}
