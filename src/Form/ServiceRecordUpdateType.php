<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\ServiceRecord;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ServiceRecordUpdateType extends AbstractType
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
            ->add('serviceDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('workDescription', TextareaType::class, [
                'constraints' => [new NotBlank(), new Length(max: 5000)],
            ])
            ->add('partsUsed', TextareaType::class, ['required' => false, 'constraints' => [new Length(max: 5000)]])
            ->add('cost', TextType::class, [
                'required' => false,
                'attr' => ['inputmode' => 'decimal'],
                'constraints' => [new Regex('/^\d{1,8}(?:\.\d{1,2})?$/')],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'In Progress' => 'In Progress',
                    'On Hold' => 'On Hold',
                    'Completed' => 'Completed',
                    'Cancelled' => 'Cancelled',
                ],
            ])
            ->add('notes', TextareaType::class, ['required' => false, 'constraints' => [new Length(max: 2000)]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceRecord::class,
            'services' => [],
        ]);
        $resolver->setAllowedTypes('services', 'array');
    }
}
