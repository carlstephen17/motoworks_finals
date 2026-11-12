<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\OrderItem;
use App\Form\ProfileType;
use App\Repository\OrderItemRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploader;
use App\Service\OrderStatusManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
            $existingEmail = $users->findOneBy(['email' => $user->getEmail()]);
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
    public function orders(OrderItemRepository $orders): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('account/order_history.html.twig', [
            'orders' => $orders->findForUser($user),
        ]);
    }

    #[Route('/order-history/{id}/cancel', name: 'order_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelOrder(int $id, Request $request, OrderItemRepository $orders, OrderStatusManager $orderStatusManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('order_cancel_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
        $order = $orders->find($id);
        if (!$order instanceof OrderItem || $order->getUser() !== $user) {
            throw $this->createNotFoundException('Order not found.');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '' || mb_strlen($reason) > 500) {
            $this->addFlash('error', 'Provide a cancellation reason of no more than 500 characters.');

            return $this->redirectToRoute('order_history');
        }

        try {
            $orderStatusManager->updateStatus($order, 'Cancelled', $reason);
            $this->addFlash('success', 'Order cancelled and stock restored.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('order_history');
    }

    #[Route('/appointments/{id}/cancel', name: 'appointment_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelAppointment(int $id, Request $request, ServiceBookingRepository $bookings, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('appointment_cancel_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $booking = $bookings->find($id);
        if (!$booking || $booking->getUser() !== $user) {
            throw $this->createNotFoundException('Appointment not found.');
        }
        if (!in_array($booking->getStatus(), ['Pending', 'Confirmed'], true)) {
            $this->addFlash('error', 'This appointment can no longer be cancelled.');
        } else {
            $booking->setStatus('Cancelled');
            $entityManager->flush();
            $this->addFlash('success', 'Appointment cancelled.');
        }

        return $this->redirectToRoute('appointment_history');
    }
}
