<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class StaffOrderItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $productChoices = [];
        foreach ($options['products'] as $product) {
            $productChoices[$product->getName().' — ₱'.$product->getPrice()] = $product->getId();
        }

        $builder
            ->add('productId', ChoiceType::class, [
                'choices' => $productChoices,
                'placeholder' => 'Choose product',
                'constraints' => [new NotBlank()],
            ])
            ->add('quantity', IntegerType::class, [
                'data' => 1,
                'attr' => ['min' => 1, 'max' => 999],
                'constraints' => [new Range(min: 1, max: 999)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['products' => []]);
        $resolver->setAllowedTypes('products', Product::class.'[]');
    }
}
