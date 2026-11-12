<?php

namespace App\Form;

use App\Entity\Product;
use App\Validator\ImageFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('price', TextType::class, [
                'attr' => ['inputmode' => 'decimal'],
                'constraints' => [new NotBlank(), new Regex('/^\d{1,8}(?:\.\d{1,2})?$/')],
            ])
            ->add('stock', IntegerType::class, ['constraints' => [new PositiveOrZero()]])
            ->add('imageFile', FileType::class, [
                'label' => 'Product image',
                'mapped' => false,
                'required' => false,
                'constraints' => [new File(maxSize: '5M'), new ImageFile()],
            ])
            ->add('active', CheckboxType::class, ['required' => false, 'label' => 'Available for sale']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
