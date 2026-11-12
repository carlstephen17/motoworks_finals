<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\ServiceBooking;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ServiceBookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choices' => $options['services'],
                'choice_label' => 'name',
                'placeholder' => 'Choose service',
            ])
            ->add('vehicleModel', TextType::class, [
                'label' => 'Motorcycle model',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('bookingDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('bookingTime', TimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Confirmed' => 'Confirmed',
                    'Completed' => 'Completed',
                    'Cancelled' => 'Cancelled',
                    'Rejected' => 'Rejected',
                ],
            ])
            ->add('notes', TextareaType::class, ['required' => false, 'constraints' => [new Length(max: 2000)]])
            ->add('rejectionReason', TextareaType::class, [
                'label' => 'Cancellation or rejection reason',
                'required' => false,
                'constraints' => [new Length(max: 1000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceBooking::class,
            'services' => [],
        ]);
        $resolver->setAllowedTypes('services', 'array');
    }
}
