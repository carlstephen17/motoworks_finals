<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\ServiceBooking;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CustomerAppointmentUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $booking = $options['data'];
        if (!$booking instanceof ServiceBooking) {
            throw new \LogicException('An existing service booking is required for customer appointment edits.');
        }

        $builder
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choices' => $options['services'],
                'choice_label' => 'name',
                'placeholder' => 'Select service',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control'],
                'mapped' => false,
                'data' => $booking->getService(),
            ])
            ->add('vehicleModel', TextType::class, [
                'label' => 'Vehicle model',
                'constraints' => [new NotBlank(), new Length(max: 255)],
                'attr' => ['class' => 'form-control'],
                'mapped' => false,
                'data' => $booking->getVehicleModel() ?? '',
            ])
            ->add('bookingDate', DateType::class, [
                'label' => 'Booking date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control', 'min' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
                'mapped' => false,
                'data' => $booking->getBookingDate(),
            ])
            ->add('bookingTime', TimeType::class, [
                'label' => 'Time slot',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-control'],
                'mapped' => false,
                'data' => $booking->getBookingTime(),
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'constraints' => [new Length(max: 2000)],
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'mapped' => false,
                'data' => $booking->getNotes() ?? '',
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
