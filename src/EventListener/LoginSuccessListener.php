<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
class LoginSuccessListener
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('auth_success', 'Login successful! Welcome back.');
        }

        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $route = match ($user->getRole()) {
            'admin' => 'admin_dashboard',
            'staff' => 'staff_dashboard',
            default => null,
        };
        if ($route !== null) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate($route)));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->security->getUser() === null && $event->getRequest()->attributes->get('_route') !== 'app_logout') {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
    }
}
