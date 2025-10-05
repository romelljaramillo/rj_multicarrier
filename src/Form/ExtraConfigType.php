<?php
/**
 * Symfony form type for extra module configuration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ExtraConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('POST')
            ->add('RJ_ETIQUETA_TRANSP_PREFIX', TextType::class, [
                'label' => 'Prefijo para etiquetas',
                'required' => false,
                'constraints' => [new Assert\Length(['max' => 32])],
            ])
            ->add('RJ_MODULE_CONTRAREEMBOLSO', ChoiceType::class, [
                'label' => 'Módulo de contrareembolso',
                'choices' => $options['module_choices'],
                'placeholder' => 'Selecciona un módulo',
                'constraints' => [new Assert\NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'module_choices' => [],
            'translation_domain' => 'Modules.RjMulticarrier.Admin',
        ]);
    }
}
