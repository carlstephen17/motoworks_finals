<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\User;
use App\Form\CustomerAppointmentUpdateType;
use App\Repository\PromoRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormInterface;
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
            ])
            ->add('vehicleModel', TextType::class, ['label' => 'Motorcycle model'])
            ->add('bookingDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('bookingTime', TimeType::class, [
                'label' => 'Time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('promoSlug', ChoiceType::class, [
                'label' => 'Promotion (optional)',
                'choices' => $promoChoices,
                'required' => false,
            ])
            ->add('promoOption', TextType::class, [
                'label' => 'Promotion option (optional)',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => [new Length(max: 2000)],
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
    public function history(ServiceBookingRepository $bookings, ServiceRepository $services): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->renderAppointmentHistory($user, $bookings, $services);
    }

    #[Route('/appointments/{id}/edit', name: 'appointment_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        ServiceBookingRepository $bookings,
        ServiceRepository $services,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $booking = $bookings->find($id);
        if (!$user instanceof User || !$booking instanceof ServiceBooking || $booking->getUser() !== $user) {
            throw $this->createNotFoundException('Appointment not found.');
        }

        if ($booking->getStatus() !== 'Pending') {
            $this->addFlash('error', 'Only pending appointments can be edited.');

            return $this->redirectToRoute('appointment_history');
        }

        $form = $this->createCustomerAppointmentUpdateForm($booking, $services);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($booking->getBookingDate()->setTime(0, 0) < new \DateTimeImmutable('today')) {
                $this->addFlash('error', 'Choose a booking date that is today or later.');

                return $this->renderAppointmentHistory($user, $bookings, $services, $form, $id);
            }

            $booking->setVehicleModel(trim((string) $booking->getVehicleModel()));
            $booking->setNotes(trim((string) $booking->getNotes()) ?: null);
            $entityManager->flush();
            $this->addFlash('success', 'Appointment updated successfully.');

            return $this->redirectToRoute('appointment_history');
        }

        return $this->renderAppointmentHistory($user, $bookings, $services, $form, $id);
    }

    #[Route('/promo-history', name: 'promo_history', methods: ['GET'])]
    public function promoHistory(ServiceBookingRepository $bookings): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('booking/promo_history.html.twig', [
            'bookings' => $bookings->findForUser($user, true),
        ]);
    }

    private function renderAppointmentHistory(
        User $user,
        ServiceBookingRepository $bookings,
        ServiceRepository $services,
        ?FormInterface $submittedEditForm = null,
        ?int $openEditId = null,
    ): Response {
        $appointments = $bookings->findForUser($user);
        $activeServices = $services->findActiveServices();
        $editForms = [];

        foreach ($appointments as $appointment) {
            $id = $appointment->getId();
            if ($id === null || $appointment->getStatus() !== 'Pending') {
                continue;
            }

            $form = $id === $openEditId && $submittedEditForm !== null
                ? $submittedEditForm
                : $this->createCustomerAppointmentUpdateForm($appointment, $services, $activeServices);
            $editForms[$id] = $form->createView();
        }

        return $this->render('booking/history.html.twig', [
            'bookings' => $appointments,
            'editForms' => $editForms,
            'openEditId' => $openEditId,
        ]);
    }

    private function createCustomerAppointmentUpdateForm(
        ServiceBooking $booking,
        ServiceRepository $services,
        ?array $activeServices = null,
    ): FormInterface {
        return $this->createForm(CustomerAppointmentUpdateType::class, $booking, [
            'services' => $activeServices ?? $services->findActiveServices(),
            'action' => $this->generateUrl('appointment_edit', ['id' => $booking->getId()]),
        ]);
    }
}
