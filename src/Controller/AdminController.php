<?php

namespace App\Controller;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Promo;
use App\Entity\PromoClaim;
use App\Entity\Service;
use App\Entity\ServiceBooking;
use App\Entity\User;
use App\Form\AdminPromoClaimUpdateType;
use App\Form\AdminUserType;
use App\Form\ProductType;
use App\Form\PromoType;
use App\Form\ServiceBookingType;
use App\Form\ServiceType;
use App\Repository\CustomerOrderRepository;
use App\Repository\OrderItemRepository;
use App\Repository\PromoClaimRepository;
use App\Repository\PromoRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\ServiceRecordRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploader;
use App\Service\AdminUserDeletionGuard;
use App\Service\OrderDeletionManager;
use App\Service\OrderStatusManager;
use App\Service\PaymentStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $users,
        ProductRepository $products,
        ServiceRepository $services,
        ServiceBookingRepository $bookings,
        CustomerOrderRepository $orders,
        PromoClaimRepository $claims,
        PromoRepository $promos,
    ): Response {
        $activity = $this->dashboardActivity($orders, $bookings, $claims);

        return $this->render('admin/dashboard.html.twig', [
            'counts' => $this->dashboardCounts($users, $products, $services, $bookings, $orders, $claims, $promos),
            ...$activity,
        ]);
    }

    #[Route('/dashboard/stats', name: 'admin_dashboard_stats', methods: ['GET'])]
    public function dashboardStats(
        UserRepository $users,
        ProductRepository $products,
        ServiceRepository $services,
        ServiceBookingRepository $bookings,
        CustomerOrderRepository $orders,
        PromoClaimRepository $claims,
        PromoRepository $promos,
    ): JsonResponse {
        $activity = $this->dashboardActivity($orders, $bookings, $claims);

        return $this->json([
            'counts' => $this->dashboardCounts($users, $products, $services, $bookings, $orders, $claims, $promos),
            'activityHtml' => $this->renderView('admin/_dashboard_activity.html.twig', $activity),
        ]);
    }

    #[Route('/products', name: 'admin_products', methods: ['GET'])]
    public function products(ProductRepository $products): Response
    {
        $productForm = $this->createForm(ProductType::class, new Product());

        return $this->render('admin/products.html.twig', [
            'products' => $products->findBy([], ['id' => 'ASC']),
            'productForm' => $productForm->createView(),
        ]);
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
        $product = $products->find($id);
        if (!$product) {
            return $this->deleteFailure($request, 'Product not found.', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_product_delete_'.$id, (string) $request->request->get('_token'))) {
            return $this->deleteFailure($request, 'Invalid security token.', Response::HTTP_FORBIDDEN);
        }

        if ($orders->count(['product' => $product]) > 0) {
            $product->setActive(false);
            $entityManager->flush();
            $message = 'Product has order history and was deactivated instead of deleted.';
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'deleted' => false, 'active' => false, 'id' => $id, 'message' => $message]);
            }
            $this->addFlash('success', $message);
        } else {
            $image = $product->getImage();
            $entityManager->remove($product);
            $entityManager->flush();
            $imageUploader->remove($image, 'uploads');
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'deleted' => true, 'id' => $id, 'message' => 'Product deleted.']);
            }
            $this->addFlash('success', 'Product deleted.');
        }

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
        $serviceForm = $this->createForm(ServiceType::class, new Service());

        return $this->render('admin/services.html.twig', [
            'services' => $services->findBy([], ['id' => 'ASC']),
            'serviceForm' => $serviceForm->createView(),
        ]);
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
        $service = $services->find($id);
        if (!$service) {
            return $this->deleteFailure($request, 'Service not found.', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_service_delete_'.$id, (string) $request->request->get('_token'))) {
            return $this->deleteFailure($request, 'Invalid security token.', Response::HTTP_FORBIDDEN);
        }

        if ($bookings->count(['service' => $service]) > 0) {
            $service->setActive(false);
            $entityManager->flush();
            $message = 'Service has booking history and was deactivated instead of deleted.';
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'deleted' => false, 'active' => false, 'id' => $id, 'message' => $message]);
            }
            $this->addFlash('success', $message);
        } else {
            $image = $service->getImage();
            $entityManager->remove($service);
            $entityManager->flush();
            $imageUploader->remove($image, 'uploads');
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'deleted' => true, 'id' => $id, 'message' => 'Service deleted.']);
            }
            $this->addFlash('success', 'Service deleted.');
        }

        return $this->redirectToRoute('admin_services');
    }

    #[Route('/promos', name: 'admin_promos', methods: ['GET'])]
    public function promos(PromoRepository $promos, PromoClaimRepository $claims): Response
    {
        $claimList = $claims->findBy([], ['id' => 'DESC']);
        $promoList = $promos->findBy([], ['title' => 'ASC']);
        $formClaim = $claimList[0] ?? new PromoClaim(
            null,
            '',
            '',
            '',
            '',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );

        return $this->render('admin/promos.html.twig', [
            'promos' => $promos->findBy([], ['id' => 'DESC']),
            'promoForm' => $this->createForm(PromoType::class, new Promo())->createView(),
            'claims' => $claimList,
            'claimUpdateForm' => $this->createForm(AdminPromoClaimUpdateType::class, $formClaim, [
                'promos' => $promoList,
            ])->createView(),
        ]);
    }

    #[Route('/promos/new', name: 'admin_promo_new', methods: ['GET', 'POST'])]
    public function promoNew(
        Request $request,
        PromoRepository $promos,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokens,
    ): Response
    {
        $promo = new Promo();
        $form = $this->createForm(PromoType::class, $promo)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyPromoFormData($form, $promo);
            if ($promos->findOneBy(['slug' => $promo->getSlug()])) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['message' => 'That promo slug is already in use.'], Response::HTTP_CONFLICT);
                }
                $this->addFlash('error', 'That promo slug is already in use.');

                return $this->render('admin/form.html.twig', ['title' => 'Create promotion', 'form' => $form]);
            }
            $entityManager->persist($promo);
            $entityManager->flush();
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'message' => 'Promotion created.',
                    'promo' => $this->promoData($promo),
                    'deleteToken' => (string) $csrfTokens->getToken('admin_promo_delete_'.$promo->getId()),
                ], Response::HTTP_CREATED);
            }
            $this->addFlash('success', 'Promotion created.');

            return $this->redirectToRoute('admin_promos');
        }

        if ($request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true, false) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json([
                'message' => $errors === [] ? 'Please check the promotion details.' : implode(' ', $errors),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/form.html.twig', ['title' => 'Create promotion', 'form' => $form]);
    }

    #[Route('/promos/{id}/edit', name: 'admin_promo_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function promoEdit(int $id, Request $request, PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $promo = $promos->find($id) ?? throw $this->createNotFoundException('Promotion not found.');
        $form = $this->createForm(PromoType::class, $promo, [
            'item_options_text' => $promo->getItemOptionsText(),
            'product_icon' => $promo->getStoredProductIcon() ?? '',
            'service_icon' => $promo->getStoredServiceIcon() ?? '',
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyPromoFormData($form, $promo);
            $slugOwner = $promos->findOneBy(['slug' => $promo->getSlug()]);
            if ($slugOwner !== null && $slugOwner->getId() !== $promo->getId()) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['message' => 'That promo slug is already in use.'], Response::HTTP_CONFLICT);
                }
                $this->addFlash('error', 'That promo slug is already in use.');

                return $this->render('admin/form.html.twig', ['title' => 'Edit promotion', 'form' => $form]);
            }
            $entityManager->flush();
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'message' => 'Promotion updated.',
                    'promo' => $this->promoData($promo),
                ]);
            }
            $this->addFlash('success', 'Promotion updated.');

            return $this->redirectToRoute('admin_promos');
        }

        if ($request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true, false) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json([
                'message' => $errors === [] ? 'Please check the promotion details.' : implode(' ', $errors),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit promotion', 'form' => $form]);
    }

    #[Route('/promos/{id}/delete', name: 'admin_promo_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoDelete(int $id, Request $request, \App\Repository\PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $promo = $promos->find($id);
        if (!$promo) {
            return $this->deleteFailure($request, 'Promotion not found.', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_promo_delete_'.$id, (string) $request->request->get('_token'))) {
            return $this->deleteFailure($request, 'Invalid security token.', Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($promo);
        $entityManager->flush();
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'deleted' => true, 'id' => $id, 'message' => 'Promotion deleted; its claim history was kept.']);
        }
        $this->addFlash('success', 'Promotion deleted; its claim history was kept.');

        return $this->redirectToRoute('admin_promos');
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['id' => 'ASC']),
            'createUserForm' => $this->createForm(AdminUserType::class, new User(), ['require_password' => true])->createView(),
            'editUserForm' => $this->createForm(AdminUserType::class, new User())->createView(),
        ]);
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
        $originalRole = $user->getRole();
        $form = $this->createForm(AdminUserType::class, $user)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($user === $this->getUser() && $user->getRole() !== 'admin') {
                $user->setRole($originalRole);
                $this->addFlash('error', 'You cannot remove your own administrator role.');

                return $this->redirectToRoute('admin_users');
            }
            $usernameOwner = $users->findOneBy(['username' => $user->getUsername()]);
            $emailOwner = $users->findOneBy(['email' => $user->getEmail()]);
            if (
                ($usernameOwner !== null && $usernameOwner->getId() !== $user->getId())
                || ($emailOwner !== null && $emailOwner->getId() !== $user->getId())
            ) {
                $this->addFlash('error', 'Username or email is already in use.');

                return $this->render('admin/form.html.twig', ['title' => 'Edit user', 'form' => $form]);
            }
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
    public function userDelete(int $id, Request $request, UserRepository $users, AdminUserDeletionGuard $deletionGuard, EntityManagerInterface $entityManager): Response
    {
        $user = $users->find($id);
        if (!$user) {
            return $this->deleteFailure($request, 'User not found.', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_user_delete_'.$id, (string) $request->request->get('_token'))) {
            return $this->deleteFailure($request, 'Invalid security token.', Response::HTTP_FORBIDDEN);
        }

        if ($user === $this->getUser()) {
            $message = 'You cannot delete your own account.';
            if ($request->isXmlHttpRequest()) {
                return $this->deleteFailure($request, $message, Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', $message);
        } elseif ($deletionGuard->hasLinkedRecords($user)) {
            $message = 'This user has linked business history and cannot be deleted.';
            if ($request->isXmlHttpRequest()) {
                return $this->deleteFailure($request, $message, Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', $message);
        } else {
            $entityManager->remove($user);
            $entityManager->flush();
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'deleted' => true, 'id' => $id, 'message' => 'User deleted.']);
            }
            $this->addFlash('success', 'User deleted.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/orders', name: 'admin_orders', methods: ['GET'])]
    public function orders(Request $request, CustomerOrderRepository $orders): Response
    {
        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->query->get('status');
        $status = is_string($status) && in_array($status, ['Pending', 'Processing', 'Cancelled', 'Completed'], true) ? $status : '';
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

        return $this->render('admin/orders.html.twig', [
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

    #[Route('/orders/{id}/status', name: 'admin_order_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderStatus(int $id, Request $request, CustomerOrderRepository $orders, OrderStatusManager $orderStatusManager): Response
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

    #[Route('/orders/{id}/payment/verify', name: 'admin_order_payment_verify', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderPaymentVerify(
        int $id,
        Request $request,
        CustomerOrderRepository $orders,
        PaymentStatusManager $paymentStatusManager,
    ): Response {
        $order = $orders->find($id) ?? throw $this->createNotFoundException('Order not found.');
        $this->assertCsrf($request, 'admin_order_payment_verify_'.$id);
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $paymentStatusManager->verify($order, $admin);
            $this->addFlash('success', 'Payment verified.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/orders/{id}/delete', name: 'admin_order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderDelete(int $id, Request $request, CustomerOrderRepository $orders, OrderDeletionManager $orderDeletionManager): Response
    {
        $order = $orders->find($id);
        if (!$order) {
            return $this->deleteFailure($request, 'Order not found.', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_order_delete_'.$id, (string) $request->request->get('_token'))) {
            return $this->deleteFailure($request, 'Invalid security token.', Response::HTTP_FORBIDDEN);
        }

        try {
            $orderDeletionManager->delete($order);
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'deleted' => true,
                    'id' => $id,
                    'message' => 'Order deleted.',
                    'counts' => $orders->getStatusCounts(),
                ]);
            }
            $this->addFlash('success', 'Order deleted.');
        } catch (\DomainException $exception) {
            if ($request->isXmlHttpRequest()) {
                return $this->deleteFailure($request, $exception->getMessage(), Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/appointments', name: 'admin_appointments', methods: ['GET'])]
    public function appointments(Request $request, ServiceBookingRepository $bookings): Response
    {
        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->query->get('status');
        $status = is_string($status) && in_array($status, ['Pending', 'Cancelled', 'Completed'], true) ? $status : '';

        return $this->render('admin/appointments.html.twig', [
            'bookings' => $bookings->findForAdmin($search, $status),
            'counts' => $bookings->getAdminStatusCounts(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    #[Route('/appointments/new', name: 'admin_appointment_new', methods: ['GET', 'POST'])]
    public function appointmentNew(
        Request $request,
        UserRepository $users,
        ServiceRepository $services,
        ServiceBookingRepository $bookings,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokens,
    ): Response
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
            ->add('status', ChoiceType::class, ['choices' => ['Pending' => 'Pending', 'Cancelled' => 'Cancelled', 'Completed' => 'Completed']])
            ->add('notes', TextareaType::class, ['required' => false])
            ->getForm()
            ->handleRequest($request);
        if ($request->isXmlHttpRequest() && !$request->isMethod('POST')) {
            return $this->render('admin/_appointment_form.html.twig', [
                'title' => 'Create Appointment',
                'form' => $form->createView(),
                'action' => $this->generateUrl('admin_appointment_new'),
            ]);
        }
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
                $booking->setStatus((string) $data['status']);
                $booking->setSource('admin');
                $booking->setCreatedByStaff($admin);
                $entityManager->persist($booking);
                $entityManager->flush();
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'message' => 'Appointment created.',
                        'booking' => $this->appointmentData($booking, $csrfTokens),
                        'counts' => $bookings->getAdminStatusCounts(),
                    ], Response::HTTP_CREATED);
                }
                $this->addFlash('success', 'Appointment created.');

                return $this->redirectToRoute('admin_appointments');
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'message' => 'Enter valid appointment details.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/form.html.twig', ['title' => 'Create appointment', 'form' => $form]);
    }

    #[Route('/appointments/{id}/status', name: 'admin_appointment_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentStatus(int $id, Request $request, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager): Response
    {
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        $this->assertCsrf($request, 'admin_appointment_status_'.$id);
        if ($booking->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This appointment is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('admin_appointments');
        }
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
    public function appointmentEdit(
        int $id,
        Request $request,
        ServiceBookingRepository $bookings,
        ServiceRepository $services,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokens,
    ): Response
    {
        $booking = $bookings->find($id) ?? throw $this->createNotFoundException('Appointment not found.');
        if ($booking->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed appointments cannot be edited.');

            return $this->redirectToRoute('admin_appointments');
        }
        if ($booking->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This appointment is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('admin_appointments');
        }

        $form = $this->createForm(ServiceBookingType::class, $booking, [
            'services' => $services->findBy([], ['name' => 'ASC']),
        ])->handleRequest($request);
        if ($request->isXmlHttpRequest() && !$request->isMethod('POST')) {
            return $this->render('admin/_appointment_form.html.twig', [
                'title' => 'Edit Appointment #'.$booking->getId(),
                'form' => $form->createView(),
                'action' => $this->generateUrl('admin_appointment_edit', ['id' => $booking->getId()]),
            ]);
        }
        if ($form->isSubmitted() && $form->isValid()) {
            if ($booking->getStatus() !== 'Cancelled') {
                $booking->setRejectionReason(null);
            } else {
                $booking->setRejectionReason(trim((string) $booking->getRejectionReason()) ?: null);
            }
            $entityManager->flush();
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'message' => 'Appointment updated.',
                    'booking' => $this->appointmentData($booking, $csrfTokens),
                    'counts' => $bookings->getAdminStatusCounts(),
                ]);
            }
            $this->addFlash('success', 'Appointment updated.');

            return $this->redirectToRoute('admin_appointments');
        }

        if ($request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true, false) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json([
                'message' => $errors === [] ? 'Please check the appointment details.' : implode(' ', $errors),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit appointment', 'form' => $form]);
    }

    #[Route('/appointments/{id}/delete', name: 'admin_appointment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentDelete(int $id, Request $request, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager): Response
    {
        $booking = $bookings->find($id);
        if (!$booking) {
            return $this->deleteFailure($request, 'Appointment not found.', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_appointment_delete_'.$id, (string) $request->request->get('_token'))) {
            return $this->deleteFailure($request, 'Invalid security token.', Response::HTTP_FORBIDDEN);
        }
        if ($booking->getStatus() === 'Cancellation Pending') {
            if ($request->isXmlHttpRequest()) {
                return $this->deleteFailure($request, 'This appointment is awaiting the customer cancellation Undo period.', Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', 'This appointment is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('admin_appointments');
        }
        $entityManager->remove($booking);
        $entityManager->flush();
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'deleted' => true,
                'id' => $id,
                'message' => 'Appointment deleted.',
                'counts' => $bookings->getAdminStatusCounts(),
            ]);
        }
        $this->addFlash('success', 'Appointment deleted.');

        return $this->redirectToRoute('admin_appointments');
    }

    #[Route('/promo-history', name: 'admin_promo_history', methods: ['GET'])]
    public function promoHistory(): Response
    {
        return $this->redirectToRoute('admin_promos', ['history' => 1]);
    }

    #[Route('/promo-history/{id}/status', name: 'admin_promo_history_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoHistoryStatus(int $id, Request $request, PromoClaimRepository $claims, EntityManagerInterface $entityManager): Response
    {
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        $this->assertCsrf($request, 'admin_promo_history_status_'.$id);
        if ($claim->getStatus() === 'Cancellation Pending') {
            $this->addFlash('error', 'This promotion claim is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('admin_promos', ['history' => 1]);
        }
        if ($claim->getStatus() === 'Completed') {
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('admin_promos', ['history' => 1]);
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['Pending', 'Confirmed', 'Used', 'Completed', 'Cancelled'], true)) {
            throw $this->createNotFoundException('Invalid claim status.');
        }
        $claim->setStatus($status);
        $entityManager->flush();
        $this->addFlash('success', 'Promotion claim status updated.');

        return $this->redirectToRoute('admin_promos', ['history' => 1]);
    }

    #[Route('/promo-history/{id}/edit', name: 'admin_promo_history_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function promoHistoryEdit(int $id, Request $request, PromoClaimRepository $claims, PromoRepository $promos, EntityManagerInterface $entityManager): Response
    {
        $claim = $claims->find($id) ?? throw $this->createNotFoundException('Promotion claim not found.');
        if ($claim->getStatus() === 'Cancellation Pending') {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['message' => 'This promotion claim is awaiting the customer cancellation Undo period.'], Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', 'This promotion claim is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('admin_promos', ['history' => 1]);
        }
        if ($claim->getStatus() === 'Completed') {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['message' => 'Completed promotion claims cannot be edited.'], Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', 'Completed promotion claims cannot be edited.');

            return $this->redirectToRoute('admin_promos', ['history' => 1]);
        }

        $form = $this->createForm(AdminPromoClaimUpdateType::class, $claim, [
            'promos' => $promos->findBy([], ['title' => 'ASC']),
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'message' => 'Promotion claim updated.',
                    'claim' => [
                        'id' => $claim->getId(),
                        'promoId' => $claim->getPromo()?->getId(),
                        'promoTitle' => $claim->getPromo()?->getTitle() ?? $claim->getBundleSlug(),
                        'bundleSlug' => $claim->getBundleSlug(),
                        'selectedOption' => $claim->getSelectedOption(),
                        'fullName' => $claim->getFullName(),
                        'phone' => $claim->getPhone(),
                        'status' => $claim->getStatus(),
                        'appointmentDate' => $claim->getAppointmentDate()->format('M j, Y'),
                        'appointmentTime' => $claim->getAppointmentTime()->format('g:i A'),
                        'createdAt' => $claim->getCreatedAt()->format('M j, Y g:i A'),
                        'details' => [
                            'id' => 'PC-'.$claim->getId(),
                            'fullName' => $claim->getFullName(),
                            'phone' => $claim->getPhone(),
                            'promotion' => $claim->getPromo()?->getTitle() ?? $claim->getBundleSlug(),
                            'bundle' => $claim->getBundleSlug(),
                            'selectedOption' => $claim->getSelectedOption(),
                            'appointmentDate' => $claim->getAppointmentDate()->format('M j, Y'),
                            'appointmentTime' => $claim->getAppointmentTime()->format('g:i A'),
                            'status' => $claim->getStatus(),
                            'createdAt' => $claim->getCreatedAt()->format('M j, Y g:i A'),
                        ],
                    ],
                ]);
            }
            $this->addFlash('success', 'Promotion claim updated.');

            return $this->redirectToRoute('admin_promos', ['history' => 1]);
        }

        if ($request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true, false) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json([
                'message' => $errors === [] ? 'Please check the promotion claim details.' : implode(' ', $errors),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/form.html.twig', ['title' => 'Edit promotion claim', 'form' => $form]);
    }

    #[Route('/promo-history/{id}/delete', name: 'admin_promo_history_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promoHistoryDelete(int $id, Request $request, PromoClaimRepository $claims, EntityManagerInterface $entityManager): Response
    {
        $claim = $claims->find($id);
        if (!$claim) {
            return $this->deleteFailure($request, 'Promotion claim not found.', Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_promo_history_delete_'.$id, (string) $request->request->get('_token'))) {
            return $this->deleteFailure($request, 'Invalid security token.', Response::HTTP_FORBIDDEN);
        }
        if ($claim->getStatus() === 'Cancellation Pending') {
            if ($request->isXmlHttpRequest()) {
                return $this->deleteFailure($request, 'This promotion claim is awaiting the customer cancellation Undo period.', Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', 'This promotion claim is awaiting the customer cancellation Undo period.');

            return $this->redirectToRoute('admin_promos', ['history' => 1]);
        }
        $entityManager->remove($claim);
        $entityManager->flush();
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'deleted' => true, 'id' => $id, 'message' => 'Promotion claim deleted.']);
        }
        $this->addFlash('success', 'Promotion claim deleted.');

        return $this->redirectToRoute('admin_promos', ['history' => 1]);
    }

    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }

    private function deleteFailure(Request $request, string $message, int $status): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => $message], $status);
        }

        if ($status === Response::HTTP_NOT_FOUND) {
            throw $this->createNotFoundException($message);
        }
        if ($status === Response::HTTP_FORBIDDEN) {
            throw $this->createAccessDeniedException($message);
        }

        throw new \Symfony\Component\HttpKernel\Exception\HttpException($status, $message);
    }

    private function dashboardCounts(
        UserRepository $users,
        ProductRepository $products,
        ServiceRepository $services,
        ServiceBookingRepository $bookings,
        CustomerOrderRepository $orders,
        PromoClaimRepository $claims,
        PromoRepository $promos,
    ): array {
        $userCount = $users->count([]);
        $productCount = $products->count([]);
        $productStatusCounts = $products->getStatusCounts();
        $serviceCount = $services->count([]);
        $appointmentCount = $bookings->count([]);
        $orderCount = $orders->count([]);
        $promoClaimCount = $claims->count([]);
        $promoCount = $promos->count([]);

        return [
            'users' => $userCount,
            'customers' => $users->countByRole('customer'),
            'staff' => $users->countByRole('staff'),
            'products' => $productCount,
            'productsActive' => $productStatusCounts['active'],
            'productsInactive' => $productStatusCounts['inactive'],
            'services' => $serviceCount,
            'promos' => $promoCount,
            'appointments' => $appointmentCount,
            'orders' => $orderCount,
            'promoClaims' => $promoClaimCount,
            'totalRecords' => $productCount + $serviceCount + $promoCount + $appointmentCount + $orderCount + $promoClaimCount,
            'paymentRecords' => $orderCount + $appointmentCount + $promoClaimCount,
        ];
    }

    /**
     * @return array{
     *     recentOrders: list<array<string, mixed>>,
     *     recentAppointments: list<ServiceBooking>,
     *     recentPromoClaims: list<PromoClaim>
     * }
     */
    private function dashboardActivity(
        CustomerOrderRepository $orders,
        ServiceBookingRepository $bookings,
        PromoClaimRepository $claims,
    ): array {
        return [
            'recentOrders' => $orders->findRecentForAdmin(),
            'recentAppointments' => $bookings->findRecentForAdmin(),
            'recentPromoClaims' => $claims->findRecentForAdmin(),
        ];
    }

    private function applyPromoFormData(FormInterface $form, Promo $promo): void
    {
        $promo->setItemOptions(trim((string) $form->get('itemOptions')->getData()));
        $promo->setProductIcon(trim((string) $form->get('productIcon')->getData()) ?: null);
        $promo->setServiceIcon(trim((string) $form->get('serviceIcon')->getData()) ?: null);
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    private function promoData(Promo $promo): array
    {
        return [
            'id' => $promo->getId(),
            'slug' => $promo->getSlug(),
            'title' => $promo->getTitle(),
            'badge' => $promo->getBadge(),
            'description' => $promo->getDescription(),
            'itemLabel' => $promo->getItemLabel(),
            'itemOptions' => $promo->getItemOptionsText(),
            'perks' => $promo->getPerks(),
            'productImage' => $promo->getProductImage(),
            'serviceImage' => $promo->getServiceImage(),
            'productIcon' => $promo->getStoredProductIcon(),
            'serviceIcon' => $promo->getStoredServiceIcon(),
            'active' => $promo->isActive(),
            'editUrl' => $this->generateUrl('admin_promo_edit', ['id' => $promo->getId()]),
            'deleteUrl' => $this->generateUrl('admin_promo_delete', ['id' => $promo->getId()]),
        ];
    }

    /**
     * @return array<string, int|string|null|array<string, string|null>>
     */
    private function appointmentData(ServiceBooking $booking, CsrfTokenManagerInterface $csrfTokens): array
    {
        $staff = $booking->getCreatedByStaff();
        $source = match ($booking->getSource()) {
            'staff' => 'Staff-Created Booking'.($staff ? ' — '.$staff->getFirstName().' '.$staff->getLastName() : ''),
            'admin' => 'Admin-Created Booking',
            default => 'Customer Online Booking',
        };
        $vehicle = $booking->getVehicleModel();
        if ($booking->getMotorcycle()?->getPlateNumber()) {
            $vehicle .= ' ('.$booking->getMotorcycle()->getPlateNumber().')';
        }
        $displayStatus = match ($booking->getStatus()) {
            'Confirmed', 'Cancellation Pending' => 'Pending',
            'Rejected' => 'Cancelled',
            default => $booking->getStatus(),
        };

        return [
            'id' => $booking->getId(),
            'sortKey' => $booking->getBookingDate()->format('Y-m-d').' '.$booking->getBookingTime()->format('H:i:s'),
            'customer' => $booking->getUser()->getFirstName().' '.$booking->getUser()->getLastName(),
            'email' => $booking->getUser()->getEmail(),
            'phone' => $booking->getUser()->getPhone() ?: 'N/A',
            'vehicle' => $vehicle,
            'service' => $booking->getService()->getName(),
            'price' => $booking->getService()->getPrice(),
            'date' => $booking->getBookingDate()->format('M j, Y'),
            'time' => $booking->getBookingTime()->format('g:i A'),
            'bundle' => $booking->getBundleSlug() ?: '—',
            'bundleOption' => $booking->getBundleOption() ?: '—',
            'notes' => $booking->getNotes() ?: '—',
            'source' => $source,
            'status' => $displayStatus,
            'rejectionReason' => $booking->getRejectionReason(),
            'details' => [
                'id' => (string) $booking->getId(),
                'customer' => $booking->getUser()->getFirstName().' '.$booking->getUser()->getLastName(),
                'email' => $booking->getUser()->getEmail(),
                'phone' => $booking->getUser()->getPhone(),
                'vehicle' => $vehicle,
                'service' => $booking->getService()->getName(),
                'price' => '₱'.number_format((float) $booking->getService()->getPrice(), 2, '.', ','),
                'date' => $booking->getBookingDate()->format('M j, Y'),
                'time' => $booking->getBookingTime()->format('g:i A'),
                'bundle' => $booking->getBundleSlug(),
                'bundleOption' => $booking->getBundleOption(),
                'status' => $displayStatus,
                'notes' => $booking->getNotes(),
                'cancellationReason' => $booking->getRejectionReason(),
                'source' => $source,
            ],
            'editUrl' => $this->generateUrl('admin_appointment_edit', ['id' => $booking->getId()]),
            'deleteUrl' => $this->generateUrl('admin_appointment_delete', ['id' => $booking->getId()]),
            'deleteToken' => (string) $csrfTokens->getToken('admin_appointment_delete_'.$booking->getId()),
        ];
    }

    private function parseDateFilter(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
