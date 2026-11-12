<?php

namespace App\Form;

use App\Entity\Promo;
use App\Entity\PromoClaim;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PromoClaimUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bundleSlug', ChoiceType::class, [
                'choices' => $options['promos'],
                'choice_label' => static fn (Promo $promo): string => $promo->getTitle(),
                'choice_value' => static fn (?Promo $promo): ?string => $promo?->getSlug(),
                'placeholder' => 'Choose promotion',
            ])
            ->add('selectedOption', TextType::class, [
                'label' => 'Selected option',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('appointmentDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('appointmentTime', TimeType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Confirmed' => 'Confirmed',
                    'Used' => 'Used',
                    'Completed' => 'Completed',
                    'Cancelled' => 'Cancelled',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PromoClaim::class,
            'promos' => [],
        ]);
        $resolver->setAllowedTypes('promos', 'array');
    }
}
