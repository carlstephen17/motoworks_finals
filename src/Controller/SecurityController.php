<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($authenticationUtils->getLastAuthenticationError()) {
            $this->addFlash('auth_error', 'Wrong username or password.');
            $this->addFlash('auth_username', $authenticationUtils->getLastUsername());
        }

        return $this->redirectToRoute('app_home', ['auth' => 'login']);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('The logout route is handled by the security firewall.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_home', ['auth' => 'register']);
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $username = strtolower(trim($user->getUsername()));
            $email = strtolower(trim($user->getEmail()));
            if ($users->findByLoginIdentifier($username) || $users->findByEmailInsensitive($email)) {
                $form->addError(new FormError('That username or email address is already registered.'));
            } else {
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setRole('customer');
                $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
                $entityManager->persist($user);
                $entityManager->flush();

                return $security->login($user, 'form_login') ?? $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }
        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('staff_dashboard');
        }

        return $this->redirectToRoute('app_home');
    }
}
