<?php

namespace App\Twig;

use App\Entity\User;
use App\Form\RegistrationType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AuthModalExtension extends AbstractExtension
{
    public function __construct(private readonly FormFactoryInterface $formFactory)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('auth_modal_form_views', $this->createFormViews(...)),
        ];
    }

    /**
     * @return array{registrationForm: \Symfony\Component\Form\FormView, forgotPasswordForm: \Symfony\Component\Form\FormView}
     */
    public function createFormViews(): array
    {
        $registrationForm = $this->formFactory->create(RegistrationType::class, new User());
        $forgotPasswordForm = $this->formFactory->createBuilder()
            ->add('email', EmailType::class, ['label' => false])
            ->getForm();

        return [
            'registrationForm' => $registrationForm->createView(),
            'forgotPasswordForm' => $forgotPasswordForm->createView(),
        ];
    }
}
