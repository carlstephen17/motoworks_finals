<?php

namespace App\Form;

use App\Entity\Promo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class PromoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 100), new Regex('/^[A-Za-z0-9_-]+$/')]])
            ->add('title', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('badge', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 50)]])
            ->add('description', TextareaType::class, ['constraints' => [new NotBlank()]])
            ->add('itemLabel', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('itemOptions', TextareaType::class, [
                'mapped' => false,
                'data' => $options['item_options_text'],
                'constraints' => [new NotBlank()],
            ])
            ->add('perks', TextareaType::class, ['required' => false])
            ->add('productImage', TextType::class, ['required' => false, 'constraints' => [new Length(max: 255)]])
            ->add('serviceImage', TextType::class, ['required' => false, 'constraints' => [new Length(max: 255)]])
            ->add('productIcon', TextType::class, [
                'mapped' => false,
                'data' => $options['product_icon'],
                'required' => false,
                'constraints' => [new Length(max: 100)],
            ])
            ->add('serviceIcon', TextType::class, [
                'mapped' => false,
                'data' => $options['service_icon'],
                'required' => false,
                'constraints' => [new Length(max: 100)],
            ])
            ->add('active', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Promo::class,
            'item_options_text' => '',
            'product_icon' => '',
            'service_icon' => '',
        ]);
        $resolver->setAllowedTypes('item_options_text', 'string');
        $resolver->setAllowedTypes('product_icon', 'string');
        $resolver->setAllowedTypes('service_icon', 'string');
    }
}
