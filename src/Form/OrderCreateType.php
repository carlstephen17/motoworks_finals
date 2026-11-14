<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $customerChoices = [];
        foreach ($options['customers'] as $customer) {
            $customerChoices[$customer->getFirstName().' '.$customer->getLastName().' (#'.$customer->getId().')'] = $customer->getId();
        }

        $builder
            ->add('customerId', ChoiceType::class, [
                'choices' => $customerChoices,
                'placeholder' => 'Choose customer',
                'constraints' => [new NotBlank()],
            ])
            ->add('deliveryAddress', TextareaType::class, [
                'constraints' => [new NotBlank(), new Length(max: 2000)],
            ])
            ->add('items', CollectionType::class, [
                'entry_type' => StaffOrderItemType::class,
                'entry_options' => ['products' => $options['products']],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'data' => [['quantity' => 1]],
                'constraints' => [new Count(min: 1, max: 20)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'customers' => [],
            'products' => [],
        ]);
        $resolver->setAllowedTypes('customers', User::class.'[]');
        $resolver->setAllowedTypes('products', Product::class.'[]');
    }
}
