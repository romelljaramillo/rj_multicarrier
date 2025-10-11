<?php
/**
 * Form type to edit carrier configuration entries.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form\Configuration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CarrierConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => $options['label_name'],
                'help' => $options['help_name'],
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'disabled' => $options['lock_name'],
            ])
            ->add('value', TextareaType::class, [
                'label' => $options['label_value'],
                'help' => $options['help_value'],
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_name' => 'Nombre',
            'help_name' => null,
            'label_value' => 'Valor',
            'help_value' => null,
            'lock_name' => false,
            'data_class' => null,
        ]);
    }
}
