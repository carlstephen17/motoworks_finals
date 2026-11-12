<?php

namespace App\Controller;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Promo;
use App\Entity\PromoClaim;
use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\User;
use App\Form\AdminUserType;
use App\Form\ProductType;
use App\Form\PromoClaimUpdateType;
use App\Form\PromoType;
use App\Form\ServiceBookingType;
use App\Form\ServiceType;
use App\Repository\OrderItemRepository;
use App\Repository\PromoClaimRepository;
use App\Repository\PromoRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploader;
use App\Service\OrderStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $users,
        ProductRepository $products,
        ServiceRepository $services,
        ServiceBookingRepository $bookings,
        OrderItemRepository $orders,
        PromoClaimRepository $claims,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'counts' => [
                'users' => $users->count([]),
                'products' => $products->count([]),
                'services' => $services->count([]),
                'appointments' => $bookings->count([]),
                'orders' => $orders->count([]),
                'promoClaims' => $claims->count([]),
            ],
        ]);
    }

    #[Route('/products', name: 'admin_products', methods: ['GET'])]
    public function products(ProductRepository $products): Response
    {
        return $this->render('admin/products.html.twig', ['products' => $products->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/products/new', name: 'admin_product_new', methods: ['GET', 'POST'])]
    public function productNew(Request $request, EntityManagerInterface $entityManager, ImageUploader $imageUploader): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('imageFile')->getData();
            if ($image instanceof UploadedFile) {
                $product->setImage($imageUploader->upload($image, 'uploads'));
            }
            $entityManager->persist($product);
            $entityManager->flush();
            $this->addFlash('success', 'Product created.');

            return $this->redirectToRoute('admin_products');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Create product', 'form' => $form]);
    }

    #[Route('/products/{id}/edit', name: 'admin_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function productEdit(int $id, Request $request, ProductRepository $products, EntityManagerInterface $entityManager, ImageUploader $imageUploader): Response
    {
        $product = $products->find($id) ?? throw $this->createNotFoundException('Product not found.');
        $form = $this->createForm(ProductType::class, $product)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $oldImage = $product->getImage();
            $image = $form->get('imageFile')->getData();
            if ($image instanceof UploadedFile) {
                $product->setImage($imageUploader->upload($image, 'uploads'));
            }
            $entityManager->flush();
            if ($image instanceof UploadedFile) {
                $imageUploader->remove($oldImage, 'uploads');
            }
            $this->addFlash('success', 'Product updated.');

            return $this->redirectToRoute('admin_products');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit product', 'form' => $form]);
    }

    #[Route('/products/{id}/delete', name: 'admin_product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function productDelete(int $id, Request $request, ProductRepository $products, OrderItemRepository $orders, EntityManagerInterface $entityManager, ImageUploader $imageUploader): Response
    {
        $product = $products->find($id) ?? throw $this->createNotFoundException('Product not found.');
        $this->assertCsrf($request, 'admin_product_delete_'.$id);
        if ($orders->count(['product' => $product]) > 0) {
            $product->setActive(false);
            $this->addFlash('success', 'Product has order history and was deactivated instead of deleted.');
        } else {
            $image = $product->getImage();
            $entityManager->remove($product);
            $entityManager->flush();
            $imageUploader->remove($image, 'uploads');
            $this->addFlash('success', 'Product deleted.');
            return $this->redirectToRoute('admin_products');
        }
        $entityManager->flush();

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/products/{id}/stock', name: 'admin_stock_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function stockUpdate(int $id, Request $request, ProductRepository $products, EntityManagerInterface $entityManager): Response
    {
        $product = $products->find($id) ?? throw $this->createNotFoundException('Product not found.');
        $this->assertCsrf($request, 'admin_stock_update_'.$id);
        $stock = filter_var($request->request->get('stock'), FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0) {
            throw $this->createNotFoundException('Stock must be a non-negative integer.');
        }
        $product->setStock($stock);
        $entityManager->flush();
        $this->addFlash('success', 'Product stock updated.');

        return $this->redirectToRoute('admin_products');
    }

    #[Route('/services', name: 'admin_services', methods: ['GET'])]
    public function services(ServiceRepository $services): Response
    {
        return $this->render('admin/services.html.twig', ['services' => $services->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/services/new', name: 'admin_service_new', methods: ['GET', 'POST'])]
    public function serviceNew(Request $request, EntityManagerInterface $entityManager, ImageUploader $imageUploader): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('imageFile')->getData();
            if ($image instanceof UploadedFile) {
                $service->setImage($imageUploader->upload($image, 'uploads'));
            }
            $entityManager->persist($service);
            $entityManager->flush();
            $this->addFlash('success', 'Service created.');

            return $this->redirectToRoute('admin_services');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Create service', 'form' => $form]);
    }

    #[Route('/services/{id}/edit', name: 'admin_service_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function serviceEdit(int $id, Request $request, ServiceRepository $services, EntityManagerInterface $entityManager, ImageUploader $imageUploader): Response
    {
        $service = $services->find($id) ?? throw $this->createNotFoundException('Service not found.');
        $form = $this->createForm(ServiceType::class, $service)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $oldImage = $service->getImage();
            $image = $form->get('imageFile')->getData();
            if ($image instanceof UploadedFile) {
                $service->setImage($imageUploader->upload($image, 'uploads'));
            }
            $entityManager->flush();
            if ($image instanceof UploadedFile) {
                $imageUploader->remove($oldImage, 'uploads');
            }
            $this->addFlash('success', 'Service updated.');

            return $this->redirectToRoute('admin_services');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit service', 'form' => $form]);
    }

    #[Route('/services/{id}/delete', name: 'admin_service_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function serviceDelete(int $id, Request $request, ServiceRepository $services, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager, ImageUploader $imageUploader): Response
    {
        $service = $services->find($id) ?? throw $this->createNotFoundException('Service not found.');
        $this->assertCsrf($request, 'admin_service_delete_'.$id);
        if ($bookings->count(['service' => $service]) > 0) {
            $service->setActive(false);
            $this->addFlash('success', 'Service has booking history and was deactivated instead of deleted.');
        } else {
            $image = $service->getImage();
            $entityManager->remove($service);
            $entityManager->flush();
            $imageUploader->remove($image, 'uploads');
            $this->addFlash('success', 'Service deleted.');
            return $this->redirectToRoute('admin_services');
        }
        $entityManager->flush();

        return $this->redirectToRoute('admin_services');
    }

    #[Route('/promos', name: 'admin_promos', methods: ['GET'])]
    public function promos(\App\Repository\PromoRepository $promos): Response
    {
        return $this->render('admin/promos.html.twig', ['promos' => $promos->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/promos/new', name: 'admin_promo_new', methods: ['GET', 'POST'])]
    public function promoNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $promo = new Promo();
        $form = $this->createForm(PromoType::class, $promo)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($promo);
            $entityManager->flush();
            $this->addFlash('success', 'Promotion created.');

            return $this->redirectToRoute('admin_promos');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Create promotion', 'form' => $form]);
    }

    #[Route('/promos/{id}/edit', name: 'admin_promo_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function promoEdit(int $id, Request $request, \App\Repository\PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $promo = $promos->find($id) ?? throw $this->createNotFoundException('Promotion not found.');
        $form = $this->createForm(PromoType::class, $promo)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Promotion updated.');

            return $this->redirectToRoute('admin_promos');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit promotion', 'form' => $form]);
    }

    #[Route('/promos/{id}/delete', name: 'admin_promo_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoDelete(int $id, Request $request, \App\Repository\PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $promo = $promos->find($id) ?? throw $this->createNotFoundException('Promotion not found.');
        $this->assertCsrf($request, 'admin_promo_delete_'.$id);
        $promo->setActive(false);
        $entityManager->flush();
        $this->addFlash('success', 'Promotion deactivated; its claim history was kept.');

        return $this->redirectToRoute('admin_promos');
    }

    #[Route('/promos/{id}/status', name: 'admin_promo_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoStatus(int $id, Request $request, \App\Repository\PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $promo = $promos->find($id) ?? throw $this->createNotFoundException('Promotion not found.');
        $this->assertCsrf($request, 'admin_promo_status_'.$id);
        $active = filter_var($request->request->get('active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($active === null) {
            throw $this->createNotFoundException('Invalid promotion status.');
        }
        $promo->setActive($active);
        $entityManager->flush();

        return $this->redirectToRoute('admin_promos');
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', ['users' => $users->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/users/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function userNew(Request $request, UserRepository $users, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user, ['require_password' => true])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            if (
                $plainPassword === ''
                || strlen($plainPassword) < 8
                || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $user->getUsername())
                || mb_strlen($user->getFirstName()) > 50
                || mb_strlen($user->getLastName()) > 50
            ) {
                $this->addFlash('error', 'Check the password, username, and name requirements.');
            } elseif ($users->findOneBy(['username' => $user->getUsername()]) || $users->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('error', 'Username or email is already in use.');
            } else {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
                $entityManager->persist($user);
                $entityManager->flush();
                $this->addFlash('success', 'User created.');

                return $this->redirectToRoute('admin_users');
            }
        }

        return $this->render('admin/form.html.twig', ['title' => 'Create user', 'form' => $form]);
    }

    #[Route('/users/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function userEdit(int $id, Request $request, UserRepository $users, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $user = $users->find($id) ?? throw $this->createNotFoundException('User not found.');
        $form = $this->createForm(AdminUserType::class, $user)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('plainPassword')->getData()) {
                $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            }
            $entityManager->flush();
            $this->addFlash('success', 'User updated.');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit user', 'form' => $form]);
    }

    #[Route('/users/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function userDelete(int $id, Request $request, UserRepository $users, OrderItemRepository $orders, ServiceBookingRepository $bookings, PromoClaimRepository $claims, EntityManagerInterface $entityManager): Response
    {
        $user = $users->find($id) ?? throw $this->createNotFoundException('User not found.');
        $this->assertCsrf($request, 'admin_user_delete_'.$id);
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'You cannot delete your own account.');
        } elseif ($orders->count(['user' => $user]) || $bookings->count(['user' => $user]) || $claims->count(['user' => $user])) {
            $this->addFlash('error', 'This user has history and cannot be deleted.');
        } else {
            $entityManager->remove($user);
            $this->addFlash('success', 'User deleted.');
        }
        $entityManager->flush();

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/orders', name: 'admin_orders', methods: ['GET'])]
    public function orders(OrderItemRepository $orders): Response
    {
        return $this->render('admin/orders.html.twig', ['orders' => $orders->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/orders/{id}/status', name: 'admin_order_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderStatus(int $id, Request $request, OrderItemRepository $orders, OrderStatusManager $orderStatusManager): Response
    {
        $order = $orders->find($id) ?? throw $this->createNotFoundException('Order not found.');
        $this->assertCsrf($request, 'admin_order_status_'.$id);
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

        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/orders/{id}/delete', name: 'admin_order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderDelete(int $id, Request $request, OrderItemRepository $orders, OrderStatusManager $orderStatusManager): Response
    {
        $order = $orders->find($id) ?? throw $this->createNotFoundException('Order not found.');
        $this->assertCsrf($request, 'admin_order_delete_'.$id);
        try {
            $orderStatusManager->updateStatus($order, 'Cancelled', (string) $request->request->get('reason', ''));
            $this->addFlash('success', 'Order cancelled and stock restored.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/appointments', name: 'admin_appointments', methods: ['GET'])]
    public function appointments(ServiceBookingRepository $bookings): Response
    {
        return $this->render('admin/appointments.html.twig', ['bookings' => $bookings->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/appointments/new', name: 'admin_appointment_new', methods: ['GET', 'POST'])]
    public function appointmentNew(Request $request, UserRepository $users, ServiceRepository $services, EntityManagerInterface $entityManager): Response
    {
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $customerChoices = [];
        foreach ($users->findBy(['role' => 'customer'], ['firstName' => 'ASC']) as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName()] = $customer->getId();
        }
        $serviceChoices = [];
        foreach ($services->findActiveServices() as $service) {
            $serviceChoices[$service->getName()] = $service->getId();
        }
        $form = $this->createFormBuilder()
            ->add('customerId', ChoiceType::class, ['choices' => $customerChoices, 'placeholder' => 'Choose customer'])
            ->add('serviceId', ChoiceType::class, ['choices' => $serviceChoices, 'placeholder' => 'Choose service'])
            ->add('vehicleModel', TextType::class, ['label' => 'Motorcycle model'])
            ->add('bookingDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('bookingTime', TimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('notes', TextareaType::class, ['required' => false])
            ->getForm()
            ->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $customer = $users->find((int) $data['customerId']);
            $service = $services->find((int) $data['serviceId']);
            if (!$customer || !$service || !$data['bookingDate'] instanceof \DateTimeImmutable || !$data['bookingTime'] instanceof \DateTimeImmutable || trim((string) $data['vehicleModel']) === '') {
                $this->addFlash('error', 'Enter valid appointment details.');
            } else {
                $booking = new ServiceBooking(
                    $customer,
                    $service,
                    trim((string) $data['vehicleModel']),
                    $data['bookingDate'],
                    $data['bookingTime'],
                    null,
                    null,
                    trim((string) ($data['notes'] ?? '')) ?: null,
                );
                $booking->setSource('staff');
                $booking->setCreatedByStaff($admin);
                $entityManager->persist($booking);
                $entityManager->flush();
                $this->addFlash('success', 'Appointment created.');

                return $this->redirectToRoute('admin_appointments');
            }
        }

        return $this->render('admin/form.html.twig', ['title' => 'Create appointment', 'form' => $form]);
    }

    #[Route('/appointments/{id}/status', name: 'admin_appointment_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentStatus(int $id, Request $request, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager): Response
    {
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        $this->assertCsrf($request, 'admin_appointment_status_'.$id);
        if ($booking->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed appointments cannot be edited.');

            return $this->redirectToRoute('admin_appointments');
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'Rejected'], true)) {
            throw $this->createNotFoundException('Invalid appointment status.');
        }
        $booking->setStatus($status);
        $entityManager->flush();
        $this->addFlash('success', 'Appointment status updated.');

        return $this->redirectToRoute('admin_appointments');
    }

    #[Route('/appointments/{id}/edit', name: 'admin_appointment_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function appointmentEdit(int $id, Request $request, ServiceBookingRepository $bookings, ServiceRepository $services, EntityManagerInterface $entityManager): Response
    {
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        if ($booking->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed appointments cannot be edited.');

            return $this->redirectToRoute('admin_appointments');
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

            return $this->redirectToRoute('admin_appointments');
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit appointment', 'form' => $form]);
    }

    #[Route('/appointments/{id}/delete', name: 'admin_appointment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentDelete(int $id, Request $request, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager): Response
    {
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        $this->assertCsrf($request, 'admin_appointment_delete_'.$id);
        $entityManager->remove($booking);
        $entityManager->flush();
        $this->addFlash('success', 'Appointment deleted.');

        return $this->redirectToRoute('admin_appointments');
    }

    #[Route('/promo-history', name: 'admin_promo_history', methods: ['GET'])]
    public function promoHistory(PromoClaimRepository $claims): Response
    {
        return $this->render('admin/promo_history.html.twig', ['claims' => $claims->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/promo-history/{id}/status', name: 'admin_promo_history_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoHistoryStatus(int $id, Request $request, PromoClaimRepository $claims, EntityManagerInterface $entityManager): Response
    {
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        $this->assertCsrf($request, 'admin_promo_history_status_'.$id);
        if ($claim->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('admin_promo_history');
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Confirmed', 'Used', 'Completed', 'Cancelled'], true)) {
            throw $this->createNotFoundException('Invalid claim status.');
        }
        $claim->setStatus($status);
        $entityManager->flush();
        $this->addFlash('success', 'Promotion claim status updated.');

        return $this->redirectToRoute('admin_promo_history');
    }

    #[Route('/promo-history/{id}/edit', name: 'admin_promo_history_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function promoHistoryEdit(int $id, Request $request, PromoClaimRepository $claims, PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        if ($claim->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('admin_promo_history');
        }

        $oldSlug = $claim->getBundleSlug();
        $oldOption = $claim->getSelectedOption();
        $form = $this->createForm(PromoClaimUpdateType::class, $claim, [
            'promos' => $promos->findBy([], ['title' => 'ASC']),
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $promo = $promos->findOneBy(['slug' => $claim->getBundleSlug()]);
            $unchangedHistoricalOption = $oldSlug === $claim->getBundleSlug() && $oldOption === $claim->getSelectedOption();
            if (!$promo || (!in_array($claim->getSelectedOption(), $promo->getItemOptions(), true) && !$unchangedHistoricalOption)) {
                $this->addFlash('error', 'The selected promotion option is unavailable.');
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'Promotion claim updated.');

                return $this->redirectToRoute('admin_promo_history');
            }
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit promotion claim', 'form' => $form]);
    }

    #[Route('/promo-history/{id}/delete', name: 'admin_promo_history_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoHistoryDelete(int $id, Request $request, PromoClaimRepository $claims, EntityManagerInterface $entityManager): Response
    {
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        $this->assertCsrf($request, 'admin_promo_history_delete_'.$id);
        $entityManager->remove($claim);
        $entityManager->flush();
        $this->addFlash('success', 'Promotion claim deleted.');

        return $this->redirectToRoute('admin_promo_history');
    }

    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }
}
