<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'cart_index', methods: ['GET'])]
    public function index(ProductRepository $products, Request $request): Response
    {
        $cart = $this->getCart($request);
        $cartProducts = [];
        $total = 0.0;

        foreach ($cart as $id => $quantity) {
            $product = $products->find($id);
            if (!$product) {
                continue;
            }
            $lineTotal = (float) $product->getPrice() * $quantity;
            $cartProducts[] = ['product' => $product, 'quantity' => $quantity, 'total' => $lineTotal];
            $total += $lineTotal;
        }

        $user = $this->getUser();
        $checkoutUser = $user instanceof User ? $user : null;
        $session = $request->getSession();
        $checkoutReceipt = $session->get('checkout_confirmation');
        $session->remove('checkout_confirmation');

        return $this->render('cart/index.html.twig', [
            'items' => $cartProducts,
            'total' => $total,
            'checkoutForm' => $this->createCheckoutForm($checkoutUser, 'cart')->createView(),
            'checkoutFullName' => $checkoutUser ? trim($checkoutUser->getFirstName().' '.$checkoutUser->getLastName()) : '',
            'checkoutReceipt' => $checkoutReceipt,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(int $id, Request $request, ProductRepository $products): Response
    {
        $isAjax = $request->isXmlHttpRequest();
        if (!$this->isGranted('ROLE_USER')) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Please log in to add items to your cart.'], Response::HTTP_UNAUTHORIZED);
            }

            throw $this->createAccessDeniedException('Please log in to add items to your cart.');
        }

        if (!$this->isCsrfTokenValid('cart_add_'.$id, (string) $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $product = $products->find($id);
        $quantity = filter_var($request->request->get('quantity', '1'), FILTER_VALIDATE_INT);
        if (!$product || !$product->isActive() || $quantity === false || $quantity < 1) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'The selected product or quantity is invalid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', 'The selected product or quantity is invalid.');

            return $this->redirectToRoute('shop_index');
        }

        $cart = $this->getCart($request);
        if (($cart[$id] ?? 0) + $quantity > $product->getStock()) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'There is not enough stock for that quantity.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', 'There is not enough stock for that quantity.');

            return $this->redirectToRoute('product_show', ['id' => $id]);
        }

        $cart[$id] = ($cart[$id] ?? 0) + $quantity;
        $request->getSession()->set('cart', $cart);
        if ($isAjax) {
            return $this->json([
                'success' => true,
                'message' => 'Product added to cart successfully!',
                'cart_count' => array_sum($cart),
            ]);
        }

        $this->addFlash('success', 'Product added to your cart.');

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/update/{id}', name: 'cart_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request, ProductRepository $products): Response
    {
        if (!$this->isCsrfTokenValid('cart_update_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $cart = $this->getCart($request);
        $quantity = filter_var($request->request->get('quantity'), FILTER_VALIDATE_INT);
        $product = $products->find($id);
        if (!isset($cart[$id])) {
            $this->addFlash('error', 'The requested quantity is not available.');
        } elseif (!$product || !$product->isActive()) {
            unset($cart[$id]);
            $request->getSession()->set('cart', $cart);
            $this->addFlash('error', 'This product is no longer available and was removed from your cart.');
        } elseif ($quantity === false || $quantity < 1 || $quantity > $product->getStock()) {
            $this->addFlash('error', 'The requested quantity is invalid or exceeds available stock.');
        } else {
            $cart[$id] = $quantity;
            $request->getSession()->set('cart', $cart);
        }

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $cart = $this->getCart($request);
        unset($cart[$id]);
        $request->getSession()->set('cart', $cart);

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/checkout/buy-now/{id}', name: 'checkout_buy_now', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function buyNow(
        int $id,
        Request $request,
        ProductRepository $products,
        CustomerOrderRepository $customerOrders,
        OrderItemRepository $orderItems,
        EntityManagerInterface $entityManager,
    ): Response {
        $isAjax = $request->isXmlHttpRequest();
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Sign in with a customer account to place an order.'], Response::HTTP_UNAUTHORIZED);
            }

            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('buy_now_'.$id, (string) $request->request->get('_token'))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $submissionKey = $request->request->get('submission_key');
        if ($submissionKey === null || $submissionKey === '') {
            $submissionKey = bin2hex(random_bytes(16));
        }
        if (!is_string($submissionKey) || !preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})$/i', $submissionKey)) {
            return $this->json(['success' => false, 'message' => 'Invalid order submission. Please try again.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $quantity = filter_var($request->request->get('quantity', '1'), FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $quantity > 999) {
            return $this->json(['success' => false, 'message' => 'Enter a valid order quantity.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existingOrder = $customerOrders->findBySubmissionKey($submissionKey);
        if ($existingOrder instanceof CustomerOrder) {
            return $this->existingBuyNowResponse($existingOrder, $user, $id, $quantity, $products, $orderItems);
        }

        $product = $products->find($id);
        if (!$product || !$product->isActive() || $quantity > $product->getStock()) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'The selected product or quantity is unavailable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', 'The selected product or quantity is unavailable.');

            return $this->redirectToRoute('product_show', ['id' => $id]);
        }

        $fullName = trim($user->getFirstName().' '.$user->getLastName());
        $phone = trim((string) $request->request->get('phone', $user->getPhone() ?? ''));
        $address = trim((string) $request->request->get('address', ''));
        $paymentMethod = trim((string) $request->request->get('payment_method', 'Cash on Delivery'));
        if ($fullName === '' || !preg_match('/^09[0-9]{9}$/', $phone)) {
            return $this->json(['success' => false, 'message' => 'Enter a valid name and 11-digit Philippine phone number before checkout.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($address === '' || mb_strlen($address) > 2000) {
            return $this->json(['success' => false, 'message' => 'Please enter your delivery address.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!in_array($paymentMethod, ['Cash on Delivery', 'GCash', 'Card'], true)) {
            return $this->json(['success' => false, 'message' => 'Invalid payment method.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $order = new CustomerOrder($user, $address, $paymentMethod, 'PENDING', $submissionKey);
            $entityManager->persist($order);
            $entityManager->flush();

            $paymentReference = ($paymentMethod === 'Cash on Delivery' ? 'MWO-' : 'MWP-')
                .(new \DateTimeImmutable())->format('Ymd').'-'.$order->getId();
            $order->setPaymentReference($paymentReference);

            $lockedProduct = $entityManager->find(Product::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$lockedProduct || !$lockedProduct->isActive() || $quantity > $lockedProduct->getStock()) {
                throw new \DomainException('The selected product is no longer available in the requested quantity.');
            }

            $unitPrice = $lockedProduct->getPrice();
            $lineTotal = number_format((float) $unitPrice * $quantity, 2, '.', '');
            $lockedProduct->setStock($lockedProduct->getStock() - $quantity);
            $createdItem = new OrderItem(
                $user,
                $lockedProduct,
                $quantity,
                $lineTotal,
                $paymentMethod,
                $paymentReference,
                $order,
                $unitPrice,
            );
            $entityManager->persist($createdItem);
            $user->setAddress($address);
            $user->setPhone($phone);
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();
            $entityManager->clear();

            return $this->json(['success' => false, 'message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (UniqueConstraintViolationException $exception) {
            $connection->rollBack();
            $entityManager->clear();
            $existingOrder = $customerOrders->findBySubmissionKey($submissionKey);
            if ($existingOrder instanceof CustomerOrder) {
                return $this->existingBuyNowResponse($existingOrder, $user, $id, $quantity, $products, $orderItems);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json($this->buyNowPayload(
            $order,
            $createdItem,
            $lockedProduct->getStock(),
        ));
    }

    private function existingBuyNowResponse(
        CustomerOrder $order,
        User $user,
        int $productId,
        int $quantity,
        ProductRepository $products,
        OrderItemRepository $orderItems,
    ): Response {
        $item = $orderItems->findOneBy(['customerOrder' => $order]);
        if (
            $order->getUser()->getId() !== $user->getId()
            || !$item instanceof OrderItem
            || $item->getProduct()->getId() !== $productId
            || $item->getQuantity() !== $quantity
        ) {
            return $this->json(['success' => false, 'message' => 'This order submission has already been used. Refresh the shop and try again.'], Response::HTTP_CONFLICT);
        }

        $currentProduct = $products->find($productId);

        return $this->json($this->buyNowPayload($order, $item, $currentProduct?->getStock() ?? 0, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function buyNowPayload(CustomerOrder $order, OrderItem $item, int $remainingStock, bool $duplicate = false): array
    {
        return [
            'success' => true,
            'message' => $duplicate ? 'This order was already recorded.' : 'Order placed successfully!',
            'duplicate' => $duplicate,
            'order_id' => $order->getId(),
            'order_status' => $order->getStatus(),
            'order_created_at' => $order->getCreatedAt()->format(DATE_ATOM),
            'total_amount' => $item->getTotalAmount(),
            'remaining_stock' => $remainingStock,
            'product_id' => $item->getProduct()->getId(),
            'payment_method' => $order->getPaymentMethod(),
            'payment_status' => $order->getPaymentStatus(),
            'payment_reference' => $order->getPaymentReference(),
            'customer_name' => trim($order->getUser()->getFirstName().' '.$order->getUser()->getLastName()),
            'phone' => $order->getUser()->getPhone() ?? '',
            'address' => $order->getDeliveryAddress(),
            'items' => [['name' => $item->getProduct()->getName(), 'quantity' => $item->getQuantity()]],
        ];
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET', 'POST'])]
    public function checkout(
        Request $request,
        ProductRepository $products,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please log in before checking out.');

            return $this->redirectToRoute('cart_index');
        }

        $isBuyNow = $request->query->getBoolean('buy_now');
        $form = $this->createCheckoutForm($user, $isBuyNow ? 'buy_now' : 'cart');
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please check the checkout details and try again.');

            return $this->redirectToRoute('cart_index');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $checkoutType = (string) $form->get('checkoutType')->getData();
            if (!in_array($checkoutType, ['cart', 'buy_now'], true)) {
                throw $this->createAccessDeniedException('Invalid checkout type.');
            }
            $cart = $this->getCart($request, $checkoutType === 'buy_now' ? 'buy_now_cart' : 'cart');
            if ($cart === []) {
                $this->addFlash('error', 'Your cart is empty.');

                return $this->redirectToRoute('cart_index');
            }

            $fullName = trim($user->getFirstName().' '.$user->getLastName());
            $phone = trim((string) $user->getPhone());
            $address = trim((string) $form->get('address')->getData());
            if ($fullName === '' || !preg_match('/^09[0-9]{9}$/', $phone)) {
                $this->addFlash('error', 'Your account must have a complete name and valid phone number before checkout.');

                return $this->redirectToRoute('cart_index');
            }

            $paymentMethod = (string) $form->get('paymentMethod')->getData();
            $connection = $entityManager->getConnection();
            $connection->beginTransaction();
            $confirmationItems = [];

            try {
                $order = new CustomerOrder($user, $address, $paymentMethod, 'PENDING');
                $entityManager->persist($order);
                $entityManager->flush();
                $paymentReference = ($paymentMethod === 'Cash on Delivery' ? 'MWO-' : 'MWP-')
                    .(new \DateTimeImmutable())->format('Ymd').'-'.$order->getId();
                $order->setPaymentReference($paymentReference);

                foreach ($cart as $productId => $quantity) {
                    $product = $entityManager->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
                    if (!$product || !$product->isActive() || $quantity > $product->getStock()) {
                        throw new \DomainException('One or more cart items are no longer available in the requested quantity.');
                    }

                    $confirmationItems[] = [
                        'name' => $product->getName(),
                        'quantity' => $quantity,
                    ];
                    $unitPrice = $product->getPrice();
                    $lineTotal = number_format((float) $unitPrice * $quantity, 2, '.', '');
                    $product->setStock($product->getStock() - $quantity);
                    $entityManager->persist(new OrderItem(
                        $user,
                        $product,
                        $quantity,
                        $lineTotal,
                        $paymentMethod,
                        $paymentReference,
                        $order,
                        $unitPrice,
                    ));
                }

                $user->setAddress($address);
                $entityManager->flush();
                $connection->commit();
                $session = $request->getSession();
                $session->remove($checkoutType === 'buy_now' ? 'buy_now_cart' : 'cart');

                if ($checkoutType === 'cart') {
                    $session->set('checkout_confirmation', [
                        'customer_name' => $fullName,
                        'phone' => $phone,
                        'address' => $address,
                        'payment_method' => $paymentMethod,
                        'reference' => $paymentReference,
                        'items' => $confirmationItems,
                    ]);

                    return $this->redirectToRoute('cart_index');
                }

                $this->addFlash('success', 'Order placed successfully. Reference: '.$paymentReference);

                return $this->redirectToRoute('order_history');
            } catch (\DomainException $exception) {
                $connection->rollBack();
                $entityManager->clear();
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('cart_index');
            } catch (\Throwable $exception) {
                $connection->rollBack();
                throw $exception;
            }
        }

        $displayCartKey = $form->isSubmitted() && $form->get('checkoutType')->getData() === 'buy_now'
            ? 'buy_now_cart'
            : ($isBuyNow ? 'buy_now_cart' : 'cart');

        return $this->render('cart/checkout.html.twig', [
            'form' => $form,
            'checkoutFullName' => trim($user->getFirstName().' '.$user->getLastName()),
            'items' => $this->cartItems(
                $this->getCart($request, $displayCartKey),
                $products,
            ),
        ]);
    }

    private function createCheckoutForm(?User $user, string $checkoutType): FormInterface
    {
        return $this->createFormBuilder([
            'checkoutType' => $checkoutType,
            'phone' => $user?->getPhone() ?? '',
            'address' => '',
            'paymentMethod' => 'Cash on Delivery',
        ], ['csrf_protection' => true])
            ->add('checkoutType', HiddenType::class)
            ->add('phone', TelType::class, [
                'label' => false,
                'attr' => [
                    'id' => 'checkout-phone',
                    'inputmode' => 'numeric',
                    'pattern' => '09[0-9]{9}',
                    'minlength' => 11,
                    'maxlength' => 11,
                    'title' => 'Enter an 11-digit Philippine mobile number starting with 09',
                ],
            ])
            ->add('address', TextareaType::class, [
                'label' => false,
                'attr' => [
                    'id' => 'checkout-address',
                    'rows' => 4,
                    'placeholder' => 'Enter your complete delivery address',
                ],
                'constraints' => [new NotBlank(), new Length(max: 2000)],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => false,
                'attr' => ['id' => 'checkout-payment'],
                'choices' => [
                    'Cash on Delivery (COD)' => 'Cash on Delivery',
                    'GCash' => 'GCash',
                    'Credit/Debit Card' => 'Card',
                ],
            ])
            ->getForm();
    }

    /**
     * @return array<int, int>
     */
    private function getCart(Request $request, string $key = 'cart'): array
    {
        $cart = $request->getSession()->get($key, []);
        if (!is_array($cart)) {
            return [];
        }

        $validCart = [];
        foreach ($cart as $id => $quantity) {
            $validId = filter_var($id, FILTER_VALIDATE_INT);
            if (is_array($quantity)) {
                $quantity = $quantity['quantity'] ?? null;
            }
            $validQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($validId && $validQuantity && $validQuantity > 0) {
                $validCart[$validId] = $validQuantity;
            }
        }

        return $validCart;
    }

    /**
     * @param array<int, int> $cart
     * @return list<array{product: Product, quantity: int, total: float}>
     */
    private function cartItems(array $cart, ProductRepository $products): array
    {
        $items = [];
        foreach ($cart as $id => $quantity) {
            $product = $products->find($id);
            if ($product && $product->isActive()) {
                $items[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'total' => (float) $product->getPrice() * $quantity,
                ];
            }
        }

        return $items;
    }
}
