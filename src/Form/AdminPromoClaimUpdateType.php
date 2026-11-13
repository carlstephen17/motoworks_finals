<?php

namespace App\Form;

use App\Entity\Promo;
use App\Entity\PromoClaim;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminPromoClaimUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('promo', EntityType::class, [
                'class' => Promo::class,
                'choices' => $options['promos'],
                'choice_label' => 'title',
                'placeholder' => 'Choose promotion',
            ])
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
