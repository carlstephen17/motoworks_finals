<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\ImageFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'First name', 'constraints' => [new NotBlank(), new Length(max: 50)]])
            ->add('lastName', TextType::class, ['label' => 'Last name', 'constraints' => [new NotBlank(), new Length(max: 50)]])
            ->add('email', EmailType::class, ['constraints' => [new NotBlank(), new Email(), new Length(max: 100)]])
            ->add('phone', TelType::class, ['required' => false, 'constraints' => [new Length(max: 20)]])
            ->add('address', TextareaType::class, ['required' => false])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile image',
                'mapped' => false,
                'required' => false,
                'constraints' => [new File(maxSize: '5M'), new ImageFile()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
