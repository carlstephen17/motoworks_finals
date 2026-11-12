<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Promo;
use App\Entity\PromoClaim;
use App\Entity\Product;
use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\ServiceRecord;
use App\Entity\User;
use App\Form\PromoClaimUpdateType;
use App\Form\ServiceBookingType;
use App\Form\ServiceRecordUpdateType;
use App\Repository\OrderItemRepository;
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
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/staff')]
class StaffController extends AbstractController
{
    #[Route('', name: 'staff_dashboard', methods: ['GET'])]
    public function dashboard(
        ServiceBookingRepository $bookings,
        OrderItemRepository $orders,
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
    public function appointments(ServiceBookingRepository $bookings): Response
    {
        return $this->render('staff/appointments.html.twig', [
            'bookings' => $bookings->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/appointments/new', name: 'staff_appointment_new', methods: ['GET', 'POST'])]
    public function appointmentNew(
        Request $request,
        UserRepository $users,
        ServiceRepository $services,
        EntityManagerInterface $entityManager,
    ): Response {
        $staff = $this->requireStaff();
        $customers = $users->findBy(['role' => 'customer'], ['firstName' => 'ASC']);
        $serviceList = $services->findActiveServices();
        $form = $this->bookingForm($customers, $serviceList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $customer = $users->find((int) $data['customerId']);
            $service = $services->find((int) $data['serviceId']);
            $date = $data['bookingDate'];
            $time = $data['bookingTime'];
            $vehicle = trim((string) $data['vehicleModel']);
            if (!$customer || !$service || !$service->isActive() || !$date instanceof \DateTimeImmutable || !$time instanceof \DateTimeImmutable || $vehicle === '') {
                $this->addFlash('error', 'Choose a customer, service, motorcycle, and valid appointment time.');
            } else {
                $booking = new ServiceBooking($customer, $service, $vehicle, $date, $time, null, null, trim((string) ($data['notes'] ?? '')) ?: null);
                $booking->setSource('staff');
                $booking->setCreatedByStaff($staff);
                $booking->setStatus('Pending');
                $entityManager->persist($booking);
                $entityManager->flush();
                $this->addFlash('success', 'Appointment created.');

                return $this->redirectToRoute('staff_appointments');
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
        $booking->setStatus($status);
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
    public function orders(OrderItemRepository $orders): Response
    {
        return $this->render('staff/orders.html.twig', ['orders' => $orders->findBy([], ['id' => 'DESC'])]);
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
            $productId = (int) $data['productId'];
            $quantity = (int) $data['quantity'];
            $product = $entityManager->find(Product::class, $productId);
            if (!$customer || !$product || !$product->isActive() || $quantity < 1) {
                $this->addFlash('error', 'Choose a valid customer, product, and quantity.');
            } else {
                $connection = $entityManager->getConnection();
                $connection->beginTransaction();
                try {
                    $product = $entityManager->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
                    if (!$product || $quantity > $product->getStock()) {
                        throw new \DomainException('The requested quantity is not available.');
                    }
                    $paymentMethod = (string) $data['paymentMethod'];
                    $reference = 'MWO-'.(new \DateTimeImmutable())->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
                    $orderGroup = new CustomerOrder($customer, $customer->getAddress() ?? '', $paymentMethod, $reference);
                    $orderGroup->setCreatedByStaff($this->requireStaff());
                    $entityManager->persist($orderGroup);
                    $entityManager->flush();
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
    public function orderStatus(int $id, Request $request, OrderItemRepository $orders, OrderStatusManager $orderStatusManager): Response
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
    public function orderDelete(int $id, Request $request, OrderItemRepository $orders, OrderStatusManager $orderStatusManager): Response
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
    public function promoHistory(PromoClaimRepository $claims): Response
    {
        return $this->render('staff/promo_history.html.twig', ['claims' => $claims->findBy([], ['id' => 'DESC'])]);
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
    public function serviceRecordNew(Request $request, UserRepository $users, ServiceRepository $services, EntityManagerInterface $entityManager): Response
    {
        $staff = $this->requireStaff();
        $customers = $users->findBy(['role' => 'customer'], ['firstName' => 'ASC']);
        $serviceList = $services->findActiveServices();
        $form = $this->serviceRecordForm($customers, $serviceList);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $customer = $users->find((int) $data['customerId']);
            $service = $services->find((int) $data['serviceId']);
            $date = $data['serviceDate'];
            $model = trim((string) $data['motorcycleModel']);
            $description = trim((string) $data['workDescription']);
            if (!$customer || !$service || !$date instanceof \DateTimeImmutable || $model === '' || $description === '') {
                $this->addFlash('error', 'Complete the customer, service, motorcycle, and work description.');
            } else {
                $record = new ServiceRecord(
                    $customer,
                    $service,
                    $staff,
                    $model,
                    $date,
                    $description,
                    trim((string) ($data['plateNumber'] ?? '')) ?: null,
                    trim((string) ($data['partsUsed'] ?? '')) ?: null,
                    trim((string) ($data['cost'] ?? '')) ?: null,
                    trim((string) ($data['notes'] ?? '')) ?: null,
                );
                $entityManager->persist($record);
                $entityManager->flush();
                $this->addFlash('success', 'Service record created.');

                return $this->redirectToRoute('staff_service_records');
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

    private function bookingForm(array $customers, array $services): \Symfony\Component\Form\FormInterface
    {
        $customerChoices = [];
        foreach ($customers as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }
        $serviceChoices = [];
        foreach ($services as $service) {
            $serviceChoices[$service->getName()] = $service->getId();
        }

        return $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('serviceId', ChoiceType::class, ['choices' => $serviceChoices, 'placeholder' => 'Choose service'])
            ->add('vehicleModel', TextType::class, ['label' => 'Motorcycle model'])
            ->add('bookingDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('bookingTime', TimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('notes', TextareaType::class, ['required' => false])
            ->getForm();
    }

    private function orderForm(array $customers, array $products): \Symfony\Component\Form\FormInterface
    {
        $customerChoices = [];
        foreach ($customers as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }
        $productChoices = [];
        foreach ($products as $product) {
            $productChoices[$product->getName().' — ₱'.$product->getPrice()] = $product->getId();
        }

        return $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('productId', ChoiceType::class, ['choices' => $productChoices, 'placeholder' => 'Choose product'])
            ->add('quantity', IntegerType::class, ['data' => 1, 'attr' => ['min' => 1]])
            ->add('paymentMethod', ChoiceType::class, ['choices' => ['Cash on Delivery' => 'Cash on Delivery', 'GCash' => 'GCash', 'Card' => 'Card']])
            ->getForm();
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

    private function serviceRecordForm(array $customers, array $services): \Symfony\Component\Form\FormInterface
    {
        $customerChoices = [];
        foreach ($customers as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }
        $serviceChoices = [];
        foreach ($services as $service) {
            $serviceChoices[$service->getName()] = $service->getId();
        }

        return $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('serviceId', ChoiceType::class, ['choices' => $serviceChoices, 'placeholder' => 'Choose service'])
            ->add('motorcycleModel', TextType::class)
            ->add('plateNumber', TextType::class, ['required' => false])
            ->add('serviceDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('workDescription', TextareaType::class)
            ->add('partsUsed', TextareaType::class, ['required' => false])
            ->add('cost', TextType::class, ['required' => false])
            ->add('notes', TextareaType::class, ['required' => false])
            ->getForm();
    }
}
