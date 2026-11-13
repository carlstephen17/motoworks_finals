<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'First name', 'constraints' => [new NotBlank(), new Length(max: 50)]])
            ->add('lastName', TextType::class, ['label' => 'Last name', 'constraints' => [new NotBlank(), new Length(max: 50)]])
            ->add('username', TextType::class, ['constraints' => [new NotBlank(), new Regex('/^[A-Za-z0-9._-]{3,50}$/')]])
            ->add('email', EmailType::class, ['constraints' => [new NotBlank(), new Email(), new Length(max: 100)]])
            ->add('phone', TelType::class, [
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '09[0-9]{9}',
                    'minlength' => 11,
                    'maxlength' => 11,
                    'title' => 'Enter an 11-digit Philippine mobile number starting with 09',
                ],
                'constraints' => [new NotBlank(), new Regex('/^09[0-9]{9}$/')],
            ])
            ->add('role', ChoiceType::class, [
                'choices' => ['Customer' => 'customer', 'Staff' => 'staff', 'Administrator' => 'admin'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $options['require_password'],
                'label' => 'Password',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => false,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
