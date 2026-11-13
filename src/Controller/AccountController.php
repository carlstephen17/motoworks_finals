<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\OrderItem;
use App\Form\ProfileType;
use App\Repository\CustomerOrderRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploader;
use App\Service\CancellationActionPresenter;
use App\Service\CancellationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    #[Route('/profile', name: 'customer_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, UserRepository $users, EntityManagerInterface $entityManager, ImageUploader $imageUploader): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $form = $this->createForm(ProfileType::class, $user)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $existingEmail = $users->findByEmailInsensitive($user->getEmail());
            if ($existingEmail && $existingEmail->getId() !== $user->getId()) {
                $this->addFlash('error', 'That email address is already in use.');
            } else {
                $oldAvatar = $user->getAvatar();
                $avatar = $form->get('avatarFile')->getData();
                if ($avatar instanceof UploadedFile) {
                    $user->setAvatar($imageUploader->upload($avatar, 'images/avatars'));
                }
                $entityManager->flush();
                if ($avatar instanceof UploadedFile) {
                    $imageUploader->remove($oldAvatar, 'images/avatars');
                }
                $this->addFlash('success', 'Your account details were updated.');
            }
        }

        return $this->render('account/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/order-history', name: 'order_history', methods: ['GET'])]
    public function orders(OrderItemRepository $orders, CancellationManager $cancellations): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }

        $pendingCancellations = $cancellations->findPendingByRecordForUser($user);

        return $this->render('account/order_history.html.twig', [
            'orders' => $orders->findForUser($user),
            'pendingCancellations' => $pendingCancellations,
        ]);
    }

    #[Route('/order-history/{id}/payment-status', name: 'order_payment_status', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function paymentStatus(int $id, CustomerOrderRepository $orders): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }

        $order = $orders->find($id);
        if (!$order || $order->getUser() !== $user) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->json([
            'payment_status' => $order->getPaymentStatus(),
            'payment_verified_at' => $order->getPaymentVerifiedAt()?->format(DATE_ATOM),
            'order_status' => $order->getStatus(),
        ]);
    }

    #[Route('/order-history/{id}/cancel', name: 'order_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelOrder(
        int $id,
        Request $request,
        OrderItemRepository $orders,
        CancellationManager $cancellations,
        CancellationActionPresenter $actionPresenter,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('order_cancel_'.$id, (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
            }
            throw $this->createAccessDeniedException('Invalid security token.');
        }
        $order = $orders->find($id);
        if (!$order instanceof OrderItem || $order->getUser() !== $user) {
            throw $this->createNotFoundException('Order not found.');
        }

        try {
            $action = $cancellations->beginOrderCancellation($order, $user);
            $payload = [
                'success' => true,
                'message' => 'Order cancellation pending.',
                'action' => $actionPresenter->present($action),
            ];
        } catch (\DomainException $exception) {
            $payload = ['success' => false, 'message' => $exception->getMessage()];
        }

        return $this->respondCancellation($request, $payload, $payload['success'] ? 200 : 409, 'order_history');
    }

    #[Route('/order-history/{id}/edit', name: 'order_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editOrder(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }

        throw $this->createAccessDeniedException('Customers may view or cancel eligible orders, but cannot edit order history.');
    }

    #[Route('/appointments/{id}/cancel', name: 'appointment_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelAppointment(
        int $id,
        Request $request,
        ServiceBookingRepository $bookings,
        CancellationManager $cancellations,
        CancellationActionPresenter $actionPresenter,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== 'customer') {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('appointment_cancel_'.$id, (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
            }
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $booking = $bookings->find($id);
        if (!$booking || $booking->getUser() !== $user) {
            throw $this->createNotFoundException('Appointment not found.');
        }
        try {
            $action = $cancellations->beginAppointmentCancellation($booking, $user);
            $payload = [
                'success' => true,
                'message' => 'Appointment cancellation pending.',
                'action' => $actionPresenter->present($action),
            ];
        } catch (\DomainException $exception) {
            $payload = ['success' => false, 'message' => $exception->getMessage()];
        }

        return $this->respondCancellation($request, $payload, $payload['success'] ? 200 : 409, 'appointment_history');
    }

    private function respondCancellation(Request $request, array $payload, int $status, string $route): Response
    {
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json($payload, $status);
        }

        $this->addFlash($payload['success'] ? 'success' : 'error', $payload['message']);

        return $this->redirectToRoute($route);
    }
}
