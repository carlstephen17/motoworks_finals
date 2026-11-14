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
        $promoChoices = [];
        foreach ($options['promos'] as $promo) {
            if (!$promo instanceof Promo) {
                throw new \InvalidArgumentException('Promotion choices must be Promo entities.');
            }
            $promoChoices[$promo->getTitle()] = $promo->getSlug();
        }

        $builder
            ->add('bundleSlug', ChoiceType::class, [
                'choices' => $promoChoices,
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
