<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetController extends AbstractController
{
    public function __construct(private readonly bool $debug)
    {
    }

    #[Route('/forgot-password', name: 'password_forgot', methods: ['GET', 'POST'])]
    public function requestReset(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
    ): Response {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, ['label' => 'Email address'])
            ->add('submit', SubmitType::class, ['label' => 'Send reset link'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim((string) $form->get('email')->getData()));
            $user = $users->findOneBy(['email' => $email]);
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $user->setResetToken(hash('sha256', $token));
                $user->setResetExpiry(new \DateTimeImmutable('+1 hour'));
                $entityManager->flush();

                $resetUrl = $this->generateUrl(
                    'password_reset',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );

                if ($this->debug) {
                    $this->addFlash('success', 'Development reset link: '.$resetUrl);
                } else {
                    try {
                        $emailMessage = (new TemplatedEmail())
                            ->from('noreply@motoworks.local')
                            ->to($user->getEmail())
                            ->subject('MotoWorks password reset')
                            ->htmlTemplate('security/reset_email.html.twig')
                            ->context(['resetUrl' => $resetUrl]);
                        $mailer->send($emailMessage);
                    } catch (TransportExceptionInterface $exception) {
                        $user->setResetToken(null);
                        $user->setResetExpiry(null);
                        $entityManager->flush();
                        throw $exception;
                    }
                }
            }

            $this->addFlash('success', 'If that email exists, reset instructions have been sent.');

            return $this->redirectToRoute('password_forgot');
        }

        return $this->render('security/forgot_password.html.twig', ['form' => $form]);
    }

    #[Route('/reset-password', name: 'password_reset', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $token = $request->query->get('token', '');
        $token = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? $token : '';
        $user = $token !== '' ? $users->findOneBy(['resetToken' => hash('sha256', $token)]) : null;
        $valid = $user instanceof User
            && $user->getResetExpiry() instanceof \DateTimeInterface
            && $user->getResetExpiry() > new \DateTimeImmutable();

        $form = $this->createFormBuilder()
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'New password'],
                'second_options' => ['label' => 'Confirm password'],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Update password'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$valid || !$user instanceof User) {
                $this->addFlash('error', 'This password reset link is invalid or has expired.');
            } else {
                $password = (string) $form->get('password')->getData();
                if (strlen($password) < 8) {
                    $this->addFlash('error', 'Password must be at least 8 characters long.');
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                    $user->setResetToken(null);
                    $user->setResetExpiry(null);
                    $entityManager->flush();
                    $this->addFlash('success', 'Your password has been reset. You can now sign in.');

                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
            'validToken' => $valid,
            'token' => $token,
        ]);
    }
}
