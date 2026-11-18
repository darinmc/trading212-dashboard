<?php

namespace App\Form;

use App\Entity\Position;
use App\Entity\Portfolio;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class PositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ticker', TextType::class, [
                'label' => 'Ticker Symbol',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. AAPL']
            ])
            ->add('name', TextType::class, [
                'label' => 'Company Name',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. Apple Inc.']
            ])
            ->add('shares', NumberType::class, [
                'label' => 'Shares',
                'scale' => 4,
                'attr' => ['class' => 'form-control', 'step' => '0.0001', 'placeholder' => 'e.g. 9400.5']
            ])
            ->add('avgPrice', TextType::class, [
                'label' => 'Average Buy Price',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. 0.1676']
            ])
            ->add('currentPrice', TextType::class, [
                'label' => 'Current Price',
                'attr' => ['class' => 'form-control', 'placeholder' => 'e.g. 0.1676']
            ])
            ->add('portfolio', EntityType::class, [
                'class' => Portfolio::class,
                'choice_label' => 'accountName',
                'label' => 'Portfolio Account',
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Position::class,
        ]);
    }
}
