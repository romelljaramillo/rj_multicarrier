<?php
/**
 * Symfony form type for shop sender configuration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form;

use PrestaShopBundle\Form\Admin\Type\ShopChoiceTreeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InfoShopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
            ->add('id_infoshop', HiddenType::class, [
                'required' => false,
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Nombre',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100]),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Apellidos',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100]),
                ],
            ])
            ->add('company', TextType::class, [
                'label' => 'Empresa',
                'required' => false,
                'constraints' => [new Assert\Length(['max' => 100])],
            ])
            ->add('additionalname', TextType::class, [
                'label' => 'Nombre adicional',
                'required' => false,
                'constraints' => [new Assert\Length(['max' => 100])],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'constraints' => [new Assert\Email(), new Assert\Length(['max' => 100])],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Teléfono',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 100])],
            ])
            ->add('vatnumber', TextType::class, [
                'label' => 'VAT',
                'required' => false,
                'constraints' => [new Assert\Length(['max' => 100])],
            ])
            ->add('street', TextType::class, [
                'label' => 'Calle',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 100])],
            ])
            ->add('number', TextType::class, [
                'label' => 'Número',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 100])],
            ])
            ->add('postcode', TextType::class, [
                'label' => 'Código postal',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 100])],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ciudad',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 255])],
            ])
            ->add('state', TextType::class, [
                'label' => 'Provincia/Estado',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 255])],
            ])
            ->add('id_country', ChoiceType::class, [
                'label' => 'País',
                'placeholder' => 'Selecciona un país',
                'choices' => $options['country_choices'],
                'choice_translation_domain' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('additionaladdress', TextType::class, [
                'label' => 'Dirección adicional',
                'required' => false,
                'constraints' => [new Assert\Length(['max' => 100])],
            ])
            ->add('isbusiness', CheckboxType::class, [
                'label' => 'Empresa',
                'required' => false,
            ]);

        if ($options['is_multistore_active']) {
            $builder->add('shop_association', ShopChoiceTreeType::class, [
                'label' => 'Asociación de tiendas',
                'required' => true,
                'translation_domain' => 'Modules.RjMulticarrier.Admin',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Selecciona al menos una tienda.',
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'country_choices' => [],
            'translation_domain' => 'Modules.RjMulticarrier.Admin',
            'is_multistore_active' => false,
        ]);
        $resolver->setAllowedTypes('is_multistore_active', 'bool');
    }
}
