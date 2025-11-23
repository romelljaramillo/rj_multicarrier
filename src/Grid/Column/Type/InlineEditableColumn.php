<?php

/**
 * Custom column type for inline-editable cells.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Column\Type;

use PrestaShop\PrestaShop\Core\Grid\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InlineEditableColumn extends AbstractColumn
{
    public function getType(): string
    {
        return 'inline_editable';
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired(['template'])
            ->setDefaults([
                'field' => null,
            ])
            ->setDefined(['type_choices', 'input', 'select', 'checkbox', 'employee_choices'])
            ->setAllowedTypes('template', 'string')
            ->setAllowedTypes('field', ['string', 'null'])
            ->setAllowedTypes('type_choices', 'array')
            ->setAllowedTypes('input', 'array')
            ->setAllowedTypes('select', 'array')
            ->setAllowedTypes('checkbox', 'array')
            ->setAllowedTypes('employee_choices', 'array');
    }
}
