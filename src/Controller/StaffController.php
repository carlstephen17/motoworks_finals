<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\Motorcycle;
use App\Entity\OrderItem;
use App\Entity\Promo;
use App\Entity\PromoClaim;
use App\Entity\Product;
use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\ServiceRecord;
use App\Entity\User;
use App\Form\PromoClaimUpdateType;
use App\Form\StaffOrderItemType;
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
use App\Service\OrderStatusManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

#[Route('/staff')]
class StaffController extends AbstractController
{
    #[Route('', name: 'staff_dashboard', methods: ['GET'])]
    public function dashboard(
        ServiceBookingRepository $bookings,
        CustomerOrderRepository $orders,
        PromoClaimRepository $claims,
        ServiceRecordRepository $records,
    ): Response {
        return $this->render('staff/dashboard.html.twig', [
            'counts' => [
                'appointments' => $bookings->count([]),
                'orders' => $orders->count([]),
                'promoClaims' => $claims->count([]),
                'serviceRecords' => $records->count([]),
            ],
        ]);
    }

    #[Route('/appointments', name: 'staff_appointments', methods: ['GET'])]
    public function appointments(Request $request, ServiceBookingRepository $bookings): Response
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
        $this->assertCsrf($request, 'staff_appointment_update_'.$id);
        if ($booking->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed appointments cannot be edited.');

            return $this->redirectToRoute('staff_appointments');
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'Rejected'], true)) {
            throw $this->createNotFoundException('Invalid appointment status.');
        }
        $rejectionReason = trim((string) $request->request->get('rejectionReason', ''));
        if (mb_strlen($rejectionReason) > 1000) {
            $this->addFlash('error', 'Cancellation reason must be no more than 1000 characters.');

            return $this->redirectToRoute('staff_appointments');
        }
        $booking->setStatus($status);
        $booking->setRejectionReason(in_array($status, ['Cancelled', 'Rejected'], true) ? ($rejectionReason ?: null) : null);
        $entityManager->flush();
        $this->addFlash('success', 'Appointment updated.');

        return $this->redirectToRoute('staff_appointments');
    }

    #[Route('/appointments/{id}/edit', name: 'staff_appointment_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function appointmentEdit(int $id, Request $request, ServiceBookingRepository $bookings, ServiceRepository $services, EntityManagerInterface $entityManager): Response
    {
        $this->requireStaff();
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        if ($booking->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed appointments cannot be edited.');

            return $this->redirectToRoute('staff_appointments');
        }

        $form = $this->createForm(ServiceBookingType::class, $booking, [
            'services' => $services->findBy([], ['name' => 'ASC']),
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
        $this->assertCsrf($request, 'staff_appointment_delete_'.$id);
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
        $paymentStatus = is_string($paymentStatus) && in_array($paymentStatus, ['Due on delivery', 'Unverified'], true) ? $paymentStatus : '';
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
        EntityManagerInterface $entityManager,
    ): Response {
        $this->requireStaff();
        $customers = $users->findBy(['role' => 'customer'], ['firstName' => 'ASC']);
        $availableProducts = $products->findActiveProducts();
        $form = $this->orderForm($customers, $availableProducts);
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
                $connection = $entityManager->getConnection();
                $connection->beginTransaction();
                try {
                    ksort($requestedItems, SORT_NUMERIC);
                    $lockedProducts = [];
                    foreach ($requestedItems as $productId => $quantity) {
                        $product = $entityManager->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
                        if (!$product instanceof Product || !$product->isActive()) {
                            throw new \DomainException('One or more selected products are unavailable.');
                        }
                        if ($quantity > $product->getStock()) {
                            throw new \DomainException($product->getName().' has only '.$product->getStock().' in stock.');
                        }
                        $lockedProducts[$productId] = $product;
                    }
                    $reference = 'MWO-'.(new \DateTimeImmutable())->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
                    $paymentMethod = 'Cash on Delivery';
                    $orderGroup = new CustomerOrder($customer, $deliveryAddress, $paymentMethod, $reference);
                    $orderGroup->setCreatedByStaff($this->requireStaff());
                    $entityManager->persist($orderGroup);
                    $entityManager->flush();
                    foreach ($requestedItems as $productId => $quantity) {
                        $product = $lockedProducts[$productId];
                        $unitPrice = $product->getPrice();
                        $product->setStock($product->getStock() - $quantity);
                        $entityManager->persist(new OrderItem(
                            $customer,
                            $product,
                            $quantity,
                            number_format((float) $unitPrice * $quantity, 2, '.', ''),
                            $paymentMethod,
                            $reference,
                            $orderGroup,
                            $unitPrice,
                        ));
                    }
                    $entityManager->flush();
                    $connection->commit();
                    $this->addFlash('success', 'Order created.');

                    return $this->redirectToRoute('staff_orders');
                } catch (\DomainException $exception) {
                    $connection->rollBack();
                    $entityManager->clear();
                    $this->addFlash('error', $exception->getMessage());
                } catch (\Throwable $exception) {
                    $connection->rollBack();
                    throw $exception;
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
        $this->assertCsrf($request, 'staff_order_status_'.$id);
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Processing', 'Shipped', 'Completed', 'Cancelled'], true)) {
            throw $this->createNotFoundException('Invalid order status.');
        }
        try {
            $orderStatusManager->updateStatus($order, $status, (string) $request->request->get('cancellationReason', ''));
            $this->addFlash('success', 'Order status updated.');
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
    public function promoHistory(Request $request, PromoClaimRepository $claims): Response
    {
        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->query->get('status');
        $status = is_string($status) && in_array($status, ['Pending', 'Confirmed', 'Completed', 'Cancelled'], true) ? $status : '';

        return $this->render('staff/promo_history.html.twig', [
            'claims' => $claims->findForStaff($search, $status),
            'counts' => $claims->getStaffStatusCounts(),
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
        $this->assertCsrf($request, 'staff_promo_claim_'.$id);
        if ($claim->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('staff_promo_history');
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Confirmed', 'Used', 'Completed', 'Cancelled'], true)) {
            throw $this->createNotFoundException('Invalid claim status.');
        }
        $claim->setStatus($status);
        $entityManager->flush();
        $this->addFlash('success', 'Promotion claim updated.');

        return $this->redirectToRoute('staff_promo_history');
    }

    #[Route('/promo-claims/{id}/edit', name: 'staff_promo_claim_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function promoClaimEdit(int $id, Request $request, PromoClaimRepository $claims, PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $this->requireStaff();
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        if ($claim->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('staff_promo_history');
        }

        $oldSlug = $claim->getBundleSlug();
        $oldOption = $claim->getSelectedOption();
        $promoList = $promos->findBy([], ['title' => 'ASC']);
        $form = $this->createForm(PromoClaimUpdateType::class, $claim, ['promos' => $promoList])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $promo = $promos->findOneBy(['slug' => $claim->getBundleSlug()]);
            $unchangedHistoricalOption = $oldSlug === $claim->getBundleSlug() && $oldOption === $claim->getSelectedOption();
            if (!$promo || (!in_array($claim->getSelectedOption(), $promo->getItemOptions(), true) && !$unchangedHistoricalOption)) {
                $this->addFlash('error', 'The selected promotion option is unavailable.');
            } else {
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
        $this->assertCsrf($request, 'staff_promo_claim_'.$id);
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
        if (!$this->isGranted('ROLE_ADMIN') && $record->getCreatedByStaff() !== $staff) {
            throw $this->createNotFoundException('Service record not found.');
        }

        return $record;
    }

    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
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

    private function orderForm(array $customers, array $products): \Symfony\Component\Form\FormInterface
    {
        $customerChoices = [];
        foreach ($customers as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }

        return $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('deliveryAddress', TextareaType::class, [
                'constraints' => [new NotBlank(), new Length(max: 2000)],
            ])
            ->add('items', CollectionType::class, [
                'entry_type' => StaffOrderItemType::class,
                'entry_options' => ['products' => $products],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'data' => [['quantity' => 1]],
                'constraints' => [new Count(min: 1, max: 20)],
            ])
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
