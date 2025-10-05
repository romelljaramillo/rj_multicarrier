<?php
/**
 * Symfony form for managing type shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TypeShipmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
            ->add('id', HiddenType::class, [
                'required' => false,
            ])
            ->add('company_id', ChoiceType::class, [
                'label' => 'Compañía',
                'choices' => $options['company_choices'],
                'placeholder' => 'Selecciona una compañía',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100]),
                ],
            ])
            ->add('business_code', TextType::class, [
                'label' => 'Código de negocio',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100]),
                ],
            ])
            ->add('reference_carrier_id', ChoiceType::class, [
                'label' => 'Transportista asociado',
                'choices' => $options['carrier_choices'],
                'placeholder' => 'Selecciona un transportista',
                'required' => false,
            ])
            ->add('active', SwitchType::class, [
                'label' => 'Activo',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar tipo de envío',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'company_choices' => [],
            'carrier_choices' => [],
            'translation_domain' => 'Modules.RjMulticarrier.Admin',
        ]);
    }
}
