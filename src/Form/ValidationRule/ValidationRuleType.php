<?php
/**
 * Formulario Symfony para gestionar reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form\ValidationRule;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ValidationRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $carrierChoiceAttr = static function ($choice, $key, $value) use ($options): array {
            $carrierIds = $options['carrier_choice_ids'] ?? [];
            $referenceId = (int) $value;
            $carrierId = $carrierIds[$referenceId] ?? null;

            if (null === $carrierId || $carrierId <= 0) {
                return [];
            }

            return ['data-carrier-id' => (string) $carrierId];
        };

        $carrierMapJson = null;

        if (!empty($options['carrier_choice_ids'])) {
            $encoded = json_encode($options['carrier_choice_ids']);

            if (false !== $encoded) {
                $carrierMapJson = $encoded;
            }
        }

        $carrierFieldAttr = static function (string $placeholder, ?string $carrierMapJson): array {
            $attr = [
                'class' => 'form-control js-validation-rule-select2',
                'data-placeholder' => $placeholder,
                'data-allow-clear' => 'true',
                'data-width' => '100%',
                'data-toggle' => 'select2',
                'data-carrier-select' => '1',
            ];

            if (null !== $carrierMapJson) {
                $attr['data-carrier-map'] = $carrierMapJson;
            }

            return $attr;
        };

        $builder
            ->setMethod('POST')
            ->add('id', HiddenType::class, [
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 191]),
                ],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'Prioridad',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Type(['type' => 'integer']),
                ],
            ])
            ->add('active', SwitchType::class, [
                'label' => 'Activa',
                'required' => false,
            ])
            ->add('product_ids', ChoiceType::class, [
                'label' => 'Productos afectados',
                'choices' => $options['product_choices'],
                'required' => false,
                'multiple' => true,
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'form-control js-validation-rule-select2',
                    'data-placeholder' => 'Selecciona productos',
                    'data-allow-clear' => 'true',
                    'data-width' => '100%',
                    'data-toggle' => 'select2',
                ],
                'help' => 'Selecciona uno o varios productos. Dejar vacío para no filtrar por productos.',
            ])
            ->add('category_ids', ChoiceType::class, [
                'label' => 'Categorías afectadas',
                'choices' => $options['category_choices'],
                'required' => false,
                'multiple' => true,
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'form-control js-validation-rule-select2',
                    'data-placeholder' => 'Selecciona categorías',
                    'data-allow-clear' => 'true',
                    'data-width' => '100%',
                    'data-toggle' => 'select2',
                ],
                'help' => 'Selecciona una o varias categorías. Dejar vacío para no filtrar por categorías.',
            ])
            ->add('zone_ids', ChoiceType::class, [
                'label' => 'Zonas permitidas',
                'choices' => $options['zone_choices'],
                'required' => false,
                'multiple' => true,
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'form-control js-validation-rule-select2',
                    'data-placeholder' => 'Selecciona zonas',
                    'data-allow-clear' => 'true',
                    'data-width' => '100%',
                    'data-toggle' => 'select2',
                ],
                'help' => 'Selecciona una o varias zonas. Dejar vacío para no filtrar por zonas.',
            ])
            ->add('country_ids', ChoiceType::class, [
                'label' => 'Países permitidos',
                'choices' => $options['country_choices'],
                'required' => false,
                'multiple' => true,
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'form-control js-validation-rule-select2',
                    'data-placeholder' => 'Selecciona países',
                    'data-allow-clear' => 'true',
                    'data-width' => '100%',
                    'data-toggle' => 'select2',
                ],
                'help' => 'Selecciona uno o varios países. Dejar vacío para no filtrar por países.',
            ])
            ->add('min_weight', NumberType::class, [
                'label' => 'Peso mínimo (kg)',
                'required' => false,
                'scale' => 3,
                'constraints' => [
                    new Assert\GreaterThanOrEqual(['value' => 0, 'message' => 'El peso mínimo debe ser mayor o igual a 0.']),
                ],
            ])
            ->add('max_weight', NumberType::class, [
                'label' => 'Peso máximo (kg)',
                'required' => false,
                'scale' => 3,
                'constraints' => [
                    new Assert\GreaterThanOrEqual(['value' => 0, 'message' => 'El peso máximo debe ser mayor o igual a 0.']),
                ],
            ])
            ->add('allow_ids', ChoiceType::class, [
                'label' => 'Transportistas permitidos',
                'choices' => $options['carrier_choices'],
                'required' => false,
                'multiple' => true,
                'choice_attr' => $carrierChoiceAttr,
                'attr' => $carrierFieldAttr('Selecciona transportistas permitidos', $carrierMapJson),
            ])
            ->add('deny_ids', ChoiceType::class, [
                'label' => 'Transportistas bloqueados',
                'choices' => $options['carrier_choices'],
                'required' => false,
                'multiple' => true,
                'choice_attr' => $carrierChoiceAttr,
                'attr' => $carrierFieldAttr('Selecciona transportistas a bloquear', $carrierMapJson),
            ])
            ->add('add_ids', ChoiceType::class, [
                'label' => 'Transportistas a añadir',
                'choices' => $options['carrier_choices'],
                'required' => false,
                'multiple' => true,
                'choice_attr' => $carrierChoiceAttr,
                'attr' => $carrierFieldAttr('Selecciona transportistas adicionales', $carrierMapJson),
            ])
            ->add('save', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'Modules.RjMulticarrier.Admin',
            'carrier_choices' => [],
            'carrier_choice_ids' => [],
            'product_choices' => [],
            'category_choices' => [],
            'zone_choices' => [],
            'country_choices' => [],
        ]);

        $resolver->setAllowedTypes('carrier_choices', 'array');
        $resolver->setAllowedTypes('carrier_choice_ids', 'array');
        $resolver->setAllowedTypes('product_choices', 'array');
        $resolver->setAllowedTypes('category_choices', 'array');
        $resolver->setAllowedTypes('zone_choices', 'array');
        $resolver->setAllowedTypes('country_choices', 'array');
    }
}
