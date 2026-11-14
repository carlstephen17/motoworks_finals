<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\Motorcycle;
use App\Entity\Promo;
use App\Entity\PromoClaim;
use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\ServiceRecord;
use App\Entity\User;
use App\Form\PromoClaimUpdateType;
use App\Form\OrderCreateType;
use App\Form\ServiceBookingType;
use App\Form\ServiceRecordUpdateType;
use App\Repository\CustomerOrderRepository;
use App\Repository\MotorcycleRepository;
use App\Repository\PromoClaimRepository;
use App\Repository\PromoRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\ServiceRecordRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\OrderCreationManager;
use App\Service\OrderStatusManager;
use App\Service\PaymentStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffController extends AbstractController
{
    #[Route('', name: 'staff_dashboard', methods: ['GET'])]
    public function dashboard(
        ProductRepository $products,
        ServiceBookingRepository $bookings,
        CustomerOrderRepository $orders,
        PromoClaimRepository $claims,
    ): Response {
        $productCount = $products->count([]);
        $appointmentCount = $bookings->count([]);
        $orderCount = $orders->count([]);
        $claimCount = $claims->count([]);

        return $this->render('staff/dashboard.html.twig', [
            'counts' => [
                'products' => $productCount,
                'appointments' => $appointmentCount,
                'orders' => $orderCount,
                'promoClaims' => $claimCount,
                'totalRecords' => $productCount + $appointmentCount + $orderCount + $claimCount,
            ],
            'recentAppointments' => $bookings->findRecentForAdmin(),
            'recentOrders' => $orders->findRecentForAdmin(),
            'recentPromoClaims' => $claims->findRecentForAdmin(),
        ]);
    }

    #[Route('/appointments', name: 'staff_appointments', methods: ['GET'])]
    public function appointments(Request $request, ServiceBookingRepository $bookings, ServiceRepository $services): Response
    {
        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->query->get('status');
        $status = is_string($status) && in_array($status, ['Pending', 'Cancelled', 'Completed'], true) ? $status : '';
        $date = $this->parseDateFilter($request->query->get('date'));
        $source = $request->query->get('source');
        $source = is_string($source) && in_array($source, ['online', 'staff', 'admin'], true) ? $source : '';

        return $this->render('staff/appointments.html.twig', [
            'bookings' => $bookings->findForStaff($search, $status, $date, $source),
            'counts' => $bookings->getAdminStatusCounts(),
            'services' => $services->findBy([], ['name' => 'ASC']),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date' => $date?->format('Y-m-d') ?? '',
                'source' => $source,
            ],
        ]);
    }

    #[Route('/appointments/new', name: 'staff_appointment_new', methods: ['GET', 'POST'])]
    public function appointmentNew(
        Request $request,
        UserRepository $users,
        MotorcycleRepository $motorcycles,
        ServiceRepository $services,
        EntityManagerInterface $entityManager,
    ): Response {
        $staff = $this->requireStaff();
        $customers = $users->findBy(['role' => 'customer'], ['firstName' => 'ASC']);
        $serviceList = $services->findActiveServices();
        $form = $this->bookingForm($customers, $serviceList, $motorcycles->findBy([], ['model' => 'ASC']));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $customer = $users->find((int) $data['customerId']);
            $service = $services->find((int) $data['serviceId']);
            $date = $data['bookingDate'];
            $time = $data['bookingTime'];
            if (!$customer || !$service || !$service->isActive() || !$date instanceof \DateTimeImmutable || !$time instanceof \DateTimeImmutable) {
                $this->addFlash('error', 'Choose a customer, service, motorcycle, and valid appointment time.');
            } else {
                try {
                    $motorcycle = $this->resolveCustomerMotorcycle(
                        $customer,
                        (string) $data['motorcycleMode'],
                        (int) ($data['motorcycleId'] ?? 0),
                        (string) ($data['motorcycleModel'] ?? ''),
                        (string) ($data['plateNumber'] ?? ''),
                        $motorcycles,
                        $entityManager,
                    );
                    $booking = new ServiceBooking(
                        $customer,
                        $service,
                        $motorcycle->getModel(),
                        $date,
                        $time,
                        null,
                        null,
                        trim((string) ($data['notes'] ?? '')) ?: null,
                    );
                    $booking->setMotorcycle($motorcycle);
                    $booking->setSource('staff');
                    $booking->setCreatedByStaff($staff);
                    $booking->setStatus('Pending');
                    $entityManager->persist($booking);
                    $entityManager->flush();
                    $this->addFlash('success', 'Appointment created.');

                    return $this->redirectToRoute('staff_appointments');
                } catch (\InvalidArgumentException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        return $this->render('staff/form.html.twig', ['title' => 'Create appointment', 'form' => $form]);
    }

    #[Route('/appointments/{id}/status', name: 'staff_appointment_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentUpdate(int $id, Request $request, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager): Response
    {
        $this->requireStaff();
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        $this->assertAppointmentOwnership($booking);
        $this->assertCsrf($request, 'staff_appointment_update_'.$id);
        if ($booking->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This appointment is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('staff_appointments');
        }
        if ($booking->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed appointments cannot be edited.');

            return $this->redirectToRoute('staff_appointments');
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Completed', 'Cancelled'], true)) {
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['success' => false, 'message' => 'Choose Pending, Completed, or Cancelled.'], Response::HTTP_UNPROCESSABLE_ENTITY)
                : throw $this->createNotFoundException('Invalid appointment status.');
        }
        $rejectionReason = trim((string) $request->request->get('rejectionReason', ''));
        if (mb_strlen($rejectionReason) > 1000) {
            $this->addFlash('error', 'Cancellation reason must be no more than 1000 characters.');

            return $this->redirectToRoute('staff_appointments');
        }
        $booking->setStatus($status);
        $booking->setRejectionReason($status === 'Cancelled' ? ($rejectionReason ?: null) : null);
        $entityManager->flush();
        if (!$request->isXmlHttpRequest()) {
            $this->addFlash('success', 'Appointment updated.');
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Appointment updated.',
                'appointment' => $this->appointmentPayload($booking),
                'counts' => $bookings->getAdminStatusCounts(),
            ]);
        }

        return $this->redirectToRoute('staff_appointments');
    }

    #[Route('/appointments/{id}/edit', name: 'staff_appointment_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function appointmentEdit(
        int $id,
        Request $request,
        ServiceBookingRepository $bookings,
        ServiceRepository $services,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->requireStaff();
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        $this->assertAppointmentOwnership($booking);
        if ($booking->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This appointment is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('staff_appointments');
        }
        if ($booking->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed appointments cannot be edited.');

            return $this->redirectToRoute('staff_appointments');
        }

        $serviceList = $services->findBy([], ['name' => 'ASC']);
        if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
            if (!$this->isCsrfTokenValid('staff_appointment_edit_'.$id, (string) $request->request->get('_token'))) {
                return new JsonResponse(['success' => false, 'message' => 'Your session token is invalid. Refresh the page and try again.'], Response::HTTP_FORBIDDEN);
            }

            $form = $formFactory->createNamed('appointment_edit', ServiceBookingType::class, $booking, [
                'services' => $serviceList,
                'status_choices' => ['Pending' => 'Pending', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'],
                'csrf_protection' => false,
            ]);
            $submitted = $request->request->all();
            $form->submit(is_array($submitted['appointment_edit'] ?? null) ? $submitted['appointment_edit'] : []);
            if (!$form->isValid()) {
                return new JsonResponse(['success' => false, 'message' => $this->firstFormError($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if ($booking->getStatus() !== 'Cancelled') {
                $booking->setRejectionReason(null);
            } else {
                $booking->setRejectionReason(trim((string) $booking->getRejectionReason()) ?: null);
            }
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Appointment updated.',
                'appointment' => $this->appointmentPayload($booking),
                'counts' => $bookings->getAdminStatusCounts(),
            ]);
        }

        $form = $this->createForm(ServiceBookingType::class, $booking, [
            'services' => $serviceList,
            'status_choices' => ['Pending' => 'Pending', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'],
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($booking->getStatus() !== 'Cancelled') {
                $booking->setRejectionReason(null);
            } else {
                $booking->setRejectionReason(trim((string) $booking->getRejectionReason()) ?: null);
            }
            $entityManager->flush();
            $this->addFlash('success', 'Appointment updated.');

            return $this->redirectToRoute('staff_appointments');
        }

        return $this->render('staff/form.html.twig', ['title' => 'Edit appointment', 'form' => $form]);
    }

    #[Route('/appointments/{id}/delete', name: 'staff_appointment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentDelete(int $id, Request $request, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager): Response
    {
        $this->requireStaff();
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        $this->assertAppointmentOwnership($booking);
        $this->assertCsrf($request, 'staff_appointment_delete_'.$id);
        if ($booking->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This appointment is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('staff_appointments');
        }
        $entityManager->remove($booking);
        $entityManager->flush();
        $this->addFlash('success', 'Appointment deleted.');

        return $this->redirectToRoute('staff_appointments');
    }

    #[Route('/orders', name: 'staff_orders', methods: ['GET'])]
    public function orders(Request $request, CustomerOrderRepository $orders): Response
    {
        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->query->get('status');
        $status = is_string($status) && in_array($status, ['Pending', 'Cancelled', 'Completed'], true) ? $status : '';
        $paymentStatus = $request->query->get('payment_status');
        $paymentStatus = is_string($paymentStatus) && in_array($paymentStatus, ['Due on delivery', 'Pending', 'Paid'], true) ? $paymentStatus : '';
        $dateFrom = $this->parseDateFilter($request->query->get('date_from'));
        $dateTo = $this->parseDateFilter($request->query->get('date_to'));
        $page = filter_var($request->query->get('page', 1), FILTER_VALIDATE_INT);
        $page = $page !== false && $page > 0 ? $page : 1;
        $perPage = 50;
        $totalOrders = $orders->countForStaff($search, $status, $paymentStatus, $dateFrom, $dateTo?->modify('+1 day'));
        $totalPages = max(1, (int) ceil($totalOrders / $perPage));
        $page = min($page, $totalPages);
        $groups = $orders->findForStaff($search, $status, $paymentStatus, $dateFrom, $dateTo?->modify('+1 day'), $perPage, ($page - 1) * $perPage);
        $groupIds = array_map(static fn (array $order): int => (int) $order['orderGroupId'], $groups);

        return $this->render('staff/orders.html.twig', [
            'orders' => $groups,
            'itemsByOrder' => $orders->findItemsForGroups($groupIds),
            'counts' => $orders->getStatusCounts(),
            'paymentVerifyRoute' => 'staff_order_payment_verify',
            'filters' => [
                'search' => $search,
                'status' => $status,
                'paymentStatus' => $paymentStatus,
                'dateFrom' => $dateFrom?->format('Y-m-d') ?? '',
                'dateTo' => $dateTo?->format('Y-m-d') ?? '',
            ],
            'page' => $page,
            'totalPages' => $totalPages,
            'totalOrders' => $totalOrders,
        ]);
    }

    #[Route('/orders/new', name: 'staff_order_new', methods: ['GET', 'POST'])]
    public function orderNew(
        Request $request,
        UserRepository $users,
        ProductRepository $products,
        OrderCreationManager $orderCreationManager,
    ): Response {
        $staff = $this->requireStaff();
        $customers = $users->findBy(['role' => 'customer'], ['firstName' => 'ASC']);
        $availableProducts = $products->findActiveProducts();
        $form = $this->createForm(OrderCreateType::class, null, [
            'customers' => $customers,
            'products' => $availableProducts,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $customer = $users->find((int) $data['customerId']);
            $deliveryAddress = trim((string) $data['deliveryAddress']);
            $requestedItems = [];
            foreach ($data['items'] as $item) {
                $productId = (int) $item['productId'];
                $requestedItems[$productId] = ($requestedItems[$productId] ?? 0) + (int) $item['quantity'];
            }
            if (
                !$customer
                || $customer->getRole() !== 'customer'
                || $deliveryAddress === ''
                || mb_strlen($deliveryAddress) > 2000
                || count($data['items']) < 1
                || count($data['items']) > 20
                || array_filter($requestedItems, static fn (int $quantity): bool => $quantity < 1 || $quantity > 999)
            ) {
                $this->addFlash('error', 'Choose a customer, delivery address, and between 1 and 20 valid order items.');
            } else {
                try {
                    $orderCreationManager->create($staff, $customer, $deliveryAddress, $requestedItems);
                    $this->addFlash('success', 'Order created.');

                    return $this->redirectToRoute('staff_orders');
                } catch (\DomainException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        return $this->render('staff/form.html.twig', ['title' => 'Create order', 'form' => $form]);
    }

    #[Route('/orders/{id}/status', name: 'staff_order_status_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderStatus(int $id, Request $request, CustomerOrderRepository $orders, OrderStatusManager $orderStatusManager): Response
    {
        $this->requireStaff();
        $order = $orders->find($id) ?? throw $this->createNotFoundException('Order not found.');
        $this->assertOrderOwnership($order);
        $this->assertCsrf($request, 'staff_order_status_'.$id);
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Completed', 'Cancelled'], true)) {
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['success' => false, 'message' => 'Choose Pending, Completed, or Cancelled.'], Response::HTTP_UNPROCESSABLE_ENTITY)
                : throw $this->createNotFoundException('Invalid order status.');
        }
        try {
            $orderStatusManager->updateStatus($order, $status, (string) $request->request->get('cancellationReason', ''));
            if (!$request->isXmlHttpRequest()) {
                $this->addFlash('success', 'Order status updated.');
            }
        } catch (\DomainException $exception) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->addFlash('error', $exception->getMessage());
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'id' => (string) $id,
                'status' => $status,
                'cancellationReason' => $order->getCancellationReason(),
                'message' => 'Order status updated.',
                'counts' => $orders->getStatusCounts(),
            ]);
        }

        return $this->redirectToRoute('staff_orders');
    }

    #[Route('/orders/{id}/payment/verify', name: 'staff_order_payment_verify', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderPaymentVerify(
        int $id,
        Request $request,
        CustomerOrderRepository $orders,
        PaymentStatusManager $paymentStatusManager,
    ): Response {
        $staff = $this->requireStaff();
        $order = $orders->find($id) ?? throw $this->createNotFoundException('Order not found.');
        $this->assertOrderOwnership($order);
        $this->assertCsrf($request, 'staff_order_payment_verify_'.$id);

        try {
            $paymentStatusManager->verify($order, $staff);
            $this->addFlash('success', 'Payment verified.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('staff_orders');
    }

    #[Route('/orders/{id}/delete', name: 'staff_order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderDelete(int $id, Request $request, CustomerOrderRepository $orders, OrderStatusManager $orderStatusManager): Response
    {
        $this->requireStaff();
        $order = $orders->find($id) ?? throw $this->createNotFoundException('Order not found.');
        $this->assertOrderOwnership($order);
        $this->assertCsrf($request, 'staff_order_delete_'.$id);
        try {
            $orderStatusManager->updateStatus($order, 'Cancelled', (string) $request->request->get('reason', ''));
            $this->addFlash('success', 'Order cancelled and stock restored.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('staff_orders');
    }

    #[Route('/promo-history', name: 'staff_promo_history', methods: ['GET'])]
    public function promoHistory(Request $request, PromoClaimRepository $claims, PromoRepository $promos): Response
    {
        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->query->get('status');
        $status = is_string($status) && in_array($status, ['Pending', 'Cancelled', 'Completed'], true) ? $status : '';

        return $this->render('staff/promo_history.html.twig', [
            'claims' => $claims->findForStaff($search, $status),
            'counts' => $claims->getStaffStatusCounts(),
            'promos' => $promos->findBy([], ['title' => 'ASC']),
            'search' => $search,
            'status' => $status,
        ]);
    }

    #[Route('/promo-claims/new', name: 'staff_promo_claim_new', methods: ['GET', 'POST'])]
    public function promoClaimNew(Request $request, UserRepository $users, PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $this->requireStaff();
        $customers = $users->findBy(['role' => 'customer'], ['firstName' => 'ASC']);
        $activePromos = $promos->findActivePromos();
        $form = $this->promoClaimForm($customers, $activePromos);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $customer = $users->find((int) $data['customerId']);
            $promo = $promos->find((int) $data['promoId']);
            $date = $data['appointmentDate'];
            $time = $data['appointmentTime'];
            $option = trim((string) $data['selectedOption']);
            if (!$customer || !$promo || !$date instanceof \DateTimeImmutable || !$time instanceof \DateTimeImmutable || !in_array($option, $promo->getItemOptions(), true)) {
                $this->addFlash('error', 'Select a customer, valid promotion option, and appointment time.');
            } else {
                $claim = new PromoClaim(
                    $customer,
                    trim($customer->getFirstName().' '.$customer->getLastName()),
                    $customer->getPhone() ?? '',
                    $promo->getSlug(),
                    $option,
                    $date,
                    $time,
                );
                $claim->setPromo($promo);
                $claim->setCreatedByStaff($this->requireStaff());
                $entityManager->persist($claim);
                $entityManager->flush();
                $this->addFlash('success', 'Promotion claim created.');

                return $this->redirectToRoute('staff_promo_history');
            }
        }

        return $this->render('staff/form.html.twig', ['title' => 'Create promotion claim', 'form' => $form]);
    }

    #[Route('/promo-claims/{id}/status', name: 'staff_promo_claim_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoClaimUpdate(int $id, Request $request, PromoClaimRepository $claims, EntityManagerInterface $entityManager): Response
    {
        $this->requireStaff();
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        $this->assertPromoClaimOwnership($claim);
        $this->assertCsrf($request, 'staff_promo_claim_'.$id);
        if ($claim->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This promotion claim is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('staff_promo_history');
        }
        if ($claim->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('staff_promo_history');
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Completed', 'Cancelled'], true)) {
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['success' => false, 'message' => 'Choose Pending, Completed, or Cancelled.'], Response::HTTP_UNPROCESSABLE_ENTITY)
                : throw $this->createNotFoundException('Invalid claim status.');
        }
        $claim->setStatus($status);
        $entityManager->flush();
        if (!$request->isXmlHttpRequest()) {
            $this->addFlash('success', 'Promotion claim updated.');
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Promotion claim updated.',
                'claim' => $this->promoClaimPayload($claim),
                'counts' => $claims->getStaffStatusCounts(),
            ]);
        }

        return $this->redirectToRoute('staff_promo_history');
    }

    #[Route('/promo-claims/{id}/edit', name: 'staff_promo_claim_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function promoClaimEdit(
        int $id,
        Request $request,
        PromoClaimRepository $claims,
        PromoRepository $promos,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->requireStaff();
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        $this->assertPromoClaimOwnership($claim);
        if ($claim->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This promotion claim is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('staff_promo_history');
        }
        if ($claim->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('staff_promo_history');
        }

        $oldSlug = $claim->getBundleSlug();
        $oldOption = $claim->getSelectedOption();
        $promoList = $promos->findBy([], ['title' => 'ASC']);
        if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
            if (!$this->isCsrfTokenValid('staff_promo_claim_edit_'.$id, (string) $request->request->get('_token'))) {
                return new JsonResponse(['success' => false, 'message' => 'Your session token is invalid. Refresh the page and try again.'], Response::HTTP_FORBIDDEN);
            }

            $form = $formFactory->createNamed('promo_claim_edit', PromoClaimUpdateType::class, $claim, [
                'promos' => $promoList,
                'csrf_protection' => false,
            ]);
            $submitted = $request->request->all();
            $form->submit(is_array($submitted['promo_claim_edit'] ?? null) ? $submitted['promo_claim_edit'] : []);
            if (!$form->isValid()) {
                return new JsonResponse(['success' => false, 'message' => $this->firstFormError($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $promo = $promos->findOneBy(['slug' => $claim->getBundleSlug()]);
            $unchangedHistoricalOption = $oldSlug === $claim->getBundleSlug() && $oldOption === $claim->getSelectedOption();
            if (!$promo || (!in_array($claim->getSelectedOption(), $promo->getItemOptions(), true) && !$unchangedHistoricalOption)) {
                return new JsonResponse(['success' => false, 'message' => 'The selected promotion option is unavailable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $claim->setPromo($promo);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Promotion claim updated.',
                'claim' => $this->promoClaimPayload($claim),
                'counts' => $claims->getStaffStatusCounts(),
            ]);
        }

        $form = $this->createForm(PromoClaimUpdateType::class, $claim, ['promos' => $promoList])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $promo = $promos->findOneBy(['slug' => $claim->getBundleSlug()]);
            $unchangedHistoricalOption = $oldSlug === $claim->getBundleSlug() && $oldOption === $claim->getSelectedOption();
            if (!$promo || (!in_array($claim->getSelectedOption(), $promo->getItemOptions(), true) && !$unchangedHistoricalOption)) {
                $this->addFlash('error', 'The selected promotion option is unavailable.');
            } else {
                $claim->setPromo($promo);
                $entityManager->flush();
                $this->addFlash('success', 'Promotion claim updated.');

                return $this->redirectToRoute('staff_promo_history');
            }
        }

        return $this->render('staff/form.html.twig', ['title' => 'Edit promotion claim', 'form' => $form]);
    }

    #[Route('/promo-claims/{id}/delete', name: 'staff_promo_claim_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoClaimDelete(int $id, Request $request, PromoClaimRepository $claims, EntityManagerInterface $entityManager): Response
    {
        $this->requireStaff();
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        $this->assertPromoClaimOwnership($claim);
        $this->assertCsrf($request, 'staff_promo_claim_'.$id);
        if ($claim->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This promotion claim is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('staff_promo_history');
        }
        $entityManager->remove($claim);
        $entityManager->flush();
        $this->addFlash('success', 'Promotion claim deleted.');

        return $this->redirectToRoute('staff_promo_history');
    }

    #[Route('/service-records', name: 'staff_service_records', methods: ['GET'])]
    public function serviceRecords(ServiceRecordRepository $records): Response
    {
        return $this->render('staff/service_records.html.twig', ['records' => $records->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/service-records/{id}/status', name: 'staff_service_record_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function serviceRecordUpdate(int $id, Request $request, ServiceRecordRepository $records, EntityManagerInterface $entityManager): Response
    {
        $record = $this->getEditableServiceRecord($id, $records);
        $this->assertCsrf($request, 'staff_service_record_'.$id);
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['In Progress', 'On Hold', 'Completed', 'Cancelled'], true)) {
            throw $this->createNotFoundException('Invalid service record status.');
        }
        $record->setStatus($status);
        $entityManager->flush();
        $this->addFlash('success', 'Service record updated.');

        return $this->redirectToRoute('staff_service_records');
    }

    #[Route('/service-records/{id}/edit', name: 'staff_service_record_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function serviceRecordEdit(int $id, Request $request, ServiceRecordRepository $records, ServiceRepository $services, EntityManagerInterface $entityManager): Response
    {
        $record = $this->getEditableServiceRecord($id, $records);
        $form = $this->createForm(ServiceRecordUpdateType::class, $record, [
            'services' => $services->findBy([], ['name' => 'ASC']),
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Service record updated.');

            return $this->redirectToRoute('staff_service_records');
        }

        return $this->render('staff/form.html.twig', ['title' => 'Edit service record', 'form' => $form]);
    }

    #[Route('/service-records/{id}/delete', name: 'staff_service_record_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function serviceRecordDelete(int $id, Request $request, ServiceRecordRepository $records, EntityManagerInterface $entityManager): Response
    {
        $record = $this->getEditableServiceRecord($id, $records);
        $this->assertCsrf($request, 'staff_service_record_delete_'.$id);
        $entityManager->remove($record);
        $entityManager->flush();
        $this->addFlash('success', 'Service record deleted.');

        return $this->redirectToRoute('staff_service_records');
    }

    #[Route('/service-records/new', name: 'staff_service_record_new', methods: ['GET', 'POST'])]
    public function serviceRecordNew(Request $request, UserRepository $users, MotorcycleRepository $motorcycles, ServiceRepository $services, EntityManagerInterface $entityManager): Response
    {
        $staff = $this->requireStaff();
        $customers = $users->findBy(['role' => 'customer'], ['firstName' => 'ASC']);
        $serviceList = $services->findActiveServices();
        $form = $this->serviceRecordForm($customers, $serviceList, $motorcycles->findBy([], ['model' => 'ASC']));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $customer = $users->find((int) $data['customerId']);
            $service = $services->find((int) $data['serviceId']);
            $date = $data['serviceDate'];
            $description = trim((string) $data['workDescription']);
            $cost = trim((string) ($data['cost'] ?? ''));
            if ($cost !== '' && preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $cost) !== 1) {
                $this->addFlash('error', 'Enter a service cost with up to 8 whole-number digits and 2 decimal places.');
            } elseif (
                !$customer
                || !$service
                || !$service->isActive()
                || !$date instanceof \DateTimeImmutable
                || $description === ''
            ) {
                $this->addFlash('error', 'Complete the customer, service, motorcycle, and work description.');
            } else {
                try {
                    $motorcycle = $this->resolveCustomerMotorcycle(
                        $customer,
                        (string) $data['motorcycleMode'],
                        (int) ($data['motorcycleId'] ?? 0),
                        (string) ($data['motorcycleModel'] ?? ''),
                        (string) ($data['plateNumber'] ?? ''),
                        $motorcycles,
                        $entityManager,
                    );
                    $record = new ServiceRecord(
                        $customer,
                        $service,
                        $staff,
                        $motorcycle->getModel(),
                        $date,
                        $description,
                        $motorcycle->getPlateNumber(),
                        trim((string) ($data['partsUsed'] ?? '')) ?: null,
                        $cost !== '' ? $cost : null,
                        trim((string) ($data['notes'] ?? '')) ?: null,
                    );
                    $record->setMotorcycle($motorcycle);
                    $entityManager->persist($record);
                    $entityManager->flush();
                    $this->addFlash('success', 'Service record created.');

                    return $this->redirectToRoute('staff_service_records');
                } catch (\InvalidArgumentException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        return $this->render('staff/form.html.twig', ['title' => 'Create service record', 'form' => $form]);
    }

    private function requireStaff(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !in_array($user->getRole(), ['staff', 'admin'], true)) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function getEditableServiceRecord(int $id, ServiceRecordRepository $records): ServiceRecord
    {
        $record = $records->find($id) ?? throw $this->createNotFoundException('Service record not found.');
        $staff = $this->requireStaff();
        if (!$this->isGranted('ROLE_ADMIN') && $record->getCreatedByStaff()->getId() !== $staff->getId()) {
            throw $this->createAccessDeniedException('You are not authorized to modify service records created by another staff member.');
        }

        return $record;
    }

    private function assertAppointmentOwnership(ServiceBooking $booking): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        $staff = $this->requireStaff();
        $creator = $booking->getCreatedByStaff();
        if ($creator !== null && $creator->getId() !== $staff->getId()) {
            throw $this->createAccessDeniedException('You are not authorized to modify or delete appointments created by another staff member.');
        }
    }

    private function assertOrderOwnership(CustomerOrder $order): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        $staff = $this->requireStaff();
        $creator = $order->getCreatedByStaff();
        if ($creator !== null && $creator->getId() !== $staff->getId()) {
            throw $this->createAccessDeniedException('You are not authorized to modify or delete orders created by another staff member.');
        }
    }

    private function assertPromoClaimOwnership(PromoClaim $claim): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        $staff = $this->requireStaff();
        $creator = $claim->getCreatedByStaff();
        if ($creator !== null && $creator->getId() !== $staff->getId()) {
            throw $this->createAccessDeniedException('You are not authorized to modify or delete promotion claims created by another staff member.');
        }
    }

    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }

    private function firstFormError(FormInterface $form): string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return 'Please check the submitted information and try again.';
    }

    private function appointmentPayload(ServiceBooking $booking): array
    {
        $motorcycle = $booking->getMotorcycle();
        $creator = $booking->getCreatedByStaff();
        $source = match ($booking->getSource()) {
            'staff' => 'Staff-Created'.($creator ? ' ('.$creator->getFirstName().' '.$creator->getLastName().')' : ''),
            'admin' => 'Admin-Created',
            default => 'Customer Online',
        };
        $vehicle = $booking->getVehicleModel() ?? '—';
        if ($motorcycle?->getPlateNumber()) {
            $vehicle .= ' ('.$motorcycle->getPlateNumber().')';
        }
        $service = $booking->getService();
        $user = $booking->getUser();
        $status = $this->panelStatus($booking->getStatus());

        return [
            'id' => '#'.$booking->getId(),
            'customer' => $user->getFirstName().' '.$user->getLastName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone() ?: 'N/A',
            'vehicle' => $vehicle,
            'service' => $service->getName(),
            'price' => '₱'.number_format((float) $service->getPrice(), 2, '.', ','),
            'date' => $booking->getBookingDate()->format('M j, Y'),
            'time' => $booking->getBookingTime()->format('g:i A'),
            'status' => $status,
            'notes' => $booking->getNotes() ?: '—',
            'cancellationReason' => $booking->getRejectionReason() ?: '—',
            'source' => $source,
            'createdAt' => $booking->getCreatedAt()?->format('M j, Y g:i A') ?? '—',
            'edit' => [
                'service' => (string) $service->getId(),
                'vehicleModel' => $booking->getVehicleModel() ?? '',
                'bookingDate' => $booking->getBookingDate()->format('Y-m-d'),
                'bookingTime' => $booking->getBookingTime()->format('H:i'),
                'status' => $status,
                'notes' => $booking->getNotes() ?? '',
                'rejectionReason' => $booking->getRejectionReason() ?? '',
            ],
        ];
    }

    private function promoClaimPayload(PromoClaim $claim): array
    {
        $promo = $claim->getPromo();
        $creator = $claim->getCreatedByStaff();
        $status = $this->panelStatus($claim->getStatus());

        return [
            'id' => 'PC-'.$claim->getId(),
            'fullName' => $claim->getFullName(),
            'phone' => $claim->getPhone() ?: 'N/A',
            'account' => $claim->getUser()?->getUsername() ?? 'Guest claim',
            'email' => $claim->getUser()?->getEmail() ?? '—',
            'promotion' => $promo?->getTitle() ?? $claim->getBundleSlug(),
            'bundle' => $claim->getBundleSlug(),
            'selectedOption' => $claim->getSelectedOption(),
            'appointmentDate' => $claim->getAppointmentDate()->format('M j, Y'),
            'appointmentTime' => $claim->getAppointmentTime()->format('g:i A'),
            'status' => $status,
            'creator' => $creator ? $creator->getFirstName().' '.$creator->getLastName() : 'Customer Online',
            'createdAt' => $claim->getCreatedAt()->format('M j, Y g:i A'),
            'edit' => [
                'bundleSlug' => $claim->getBundleSlug(),
                'selectedOption' => $claim->getSelectedOption(),
                'appointmentDate' => $claim->getAppointmentDate()->format('Y-m-d'),
                'appointmentTime' => $claim->getAppointmentTime()->format('H:i'),
                'status' => $status,
            ],
        ];
    }

    private function panelStatus(?string $status): string
    {
        return match ($status) {
            'Completed' => 'Completed',
            'Cancelled', 'Rejected' => 'Cancelled',
            default => 'Pending',
        };
    }

    private function bookingForm(array $customers, array $services, array $motorcycles): \Symfony\Component\Form\FormInterface
    {
        $customerChoices = [];
        foreach ($customers as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }
        $serviceChoices = [];
        foreach ($services as $service) {
            $serviceChoices[$service->getName()] = $service->getId();
        }

        $form = $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('serviceId', ChoiceType::class, ['choices' => $serviceChoices, 'placeholder' => 'Choose service']);
        $this->addMotorcycleFields($form, $motorcycles);

        return $form
            ->add('bookingDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('bookingTime', TimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('notes', TextareaType::class, ['required' => false, 'constraints' => [new Length(max: 2000)]])
            ->getForm();
    }

    private function parseDateFilter(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function promoClaimForm(array $customers, array $promos): \Symfony\Component\Form\FormInterface
    {
        $customerChoices = [];
        foreach ($customers as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }
        $promoChoices = [];
        foreach ($promos as $promo) {
            $promoChoices[$promo->getTitle()] = $promo->getId();
        }

        return $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('promoId', ChoiceType::class, ['choices' => $promoChoices, 'placeholder' => 'Choose promotion'])
            ->add('selectedOption', TextType::class)
            ->add('appointmentDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('appointmentTime', TimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->getForm();
    }

    private function serviceRecordForm(array $customers, array $services, array $motorcycles): \Symfony\Component\Form\FormInterface
    {
        $customerChoices = [];
        foreach ($customers as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }
        $serviceChoices = [];
        foreach ($services as $service) {
            $serviceChoices[$service->getName()] = $service->getId();
        }

        $form = $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('serviceId', ChoiceType::class, ['choices' => $serviceChoices, 'placeholder' => 'Choose service']);
        $this->addMotorcycleFields($form, $motorcycles);

        return $form
            ->add('serviceDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('workDescription', TextareaType::class, ['constraints' => [new NotBlank(), new Length(max: 5000)]])
            ->add('partsUsed', TextareaType::class, ['required' => false, 'constraints' => [new Length(max: 5000)]])
            ->add('cost', TextType::class, [
                'required' => false,
                'empty_data' => null,
                'attr' => ['maxlength' => 11, 'inputmode' => 'decimal'],
                'constraints' => [new Regex('/^\d{1,8}(?:\.\d{1,2})?$/')],
            ])
            ->add('notes', TextareaType::class, ['required' => false, 'constraints' => [new Length(max: 2000)]])
            ->getForm();
    }

    private function addMotorcycleFields(FormBuilderInterface $form, array $motorcycles): void
    {
        $motorcycleChoices = [];
        $motorcycleCustomerIds = [];
        foreach ($motorcycles as $motorcycle) {
            if (!$motorcycle instanceof Motorcycle || $motorcycle->getId() === null) {
                continue;
            }
            $label = $motorcycle->getModel();
            if ($motorcycle->getPlateNumber()) {
                $label .= ' — '.$motorcycle->getPlateNumber();
            }
            $motorcycleChoices['#'.$motorcycle->getId().' '.$label] = $motorcycle->getId();
            $motorcycleCustomerIds[$motorcycle->getId()] = $motorcycle->getUser()->getId();
        }

        $form
            ->add('motorcycleMode', ChoiceType::class, [
                'label' => 'Motorcycle',
                'choices' => [
                    'Choose registered motorcycle' => 'existing',
                    'Register motorcycle for this customer' => 'new',
                ],
                'data' => 'existing',
            ])
            ->add('motorcycleId', ChoiceType::class, [
                'label' => 'Registered motorcycle',
                'choices' => $motorcycleChoices,
                'placeholder' => 'Select motorcycle',
                'required' => false,
                'choice_attr' => static fn (mixed $choice, string $key, mixed $value): array => [
                    'data-customer' => (string) ($motorcycleCustomerIds[(int) $value] ?? ''),
                ],
            ])
            ->add('motorcycleModel', TextType::class, [
                'label' => 'Motorcycle model',
                'required' => false,
                'attr' => ['maxlength' => 255],
                'constraints' => [new Length(max: 255)],
            ])
            ->add('plateNumber', TextType::class, [
                'label' => 'Plate number',
                'required' => false,
                'attr' => ['maxlength' => 32],
                'constraints' => [new Length(max: 32)],
            ]);
    }

    private function resolveCustomerMotorcycle(
        User $customer,
        string $mode,
        int $motorcycleId,
        string $model,
        string $plate,
        MotorcycleRepository $motorcycles,
        EntityManagerInterface $entityManager,
    ): Motorcycle {
        if ($mode === 'existing') {
            $motorcycle = $motorcycleId > 0 ? $motorcycles->find($motorcycleId) : null;
            if (!$motorcycle instanceof Motorcycle || $motorcycle->getUser() !== $customer) {
                throw new \InvalidArgumentException('Choose a motorcycle that belongs to the selected customer.');
            }

            return $motorcycle;
        }
        if ($mode !== 'new') {
            throw new \InvalidArgumentException('Choose a valid motorcycle option.');
        }

        $model = trim($model);
        $plate = strtoupper(trim($plate));
        if ($model === '' || mb_strlen($model) > 255 || mb_strlen($plate) > 32) {
            throw new \InvalidArgumentException('Enter a valid motorcycle model and plate number.');
        }

        if ($plate !== '') {
            $existingPlate = $motorcycles->findByPlateNumber($plate);
            if ($existingPlate instanceof Motorcycle) {
                if (
                    $existingPlate->getUser() === $customer
                    && strtolower(trim($existingPlate->getModel())) === strtolower($model)
                ) {
                    return $existingPlate;
                }
                throw new \InvalidArgumentException('This plate number is already registered.');
            }
        }

        $motorcycle = $motorcycles->findDuplicate($customer, $model, $plate !== '' ? $plate : null);
        if ($motorcycle instanceof Motorcycle) {
            return $motorcycle;
        }

        $motorcycle = new Motorcycle($customer, $model, $plate !== '' ? $plate : null);
        $entityManager->persist($motorcycle);

        return $motorcycle;
    }
}
