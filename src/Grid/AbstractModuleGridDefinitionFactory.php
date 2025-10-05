<?php
/**
 * Shared base grid definition factory with safe translation helpers.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid;

use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use Stringable;

abstract class AbstractModuleGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    protected const DEFAULT_DOMAIN = 'Modules.RjMulticarrier.Admin';

    /**
     * Translate message ensuring a string is always returned.
     */
    protected function transString(string $id, array $parameters = [], ?string $domain = self::DEFAULT_DOMAIN): string
    {
        $translation = $this->trans($id, $parameters, $domain);

        if (is_string($translation)) {
            return $translation;
        }

        if ($translation instanceof Stringable) {
            return (string) $translation;
        }

        if (is_array($translation)) {
            $first = reset($translation);

            if (is_string($first)) {
                return $first;
            }

            if ($first instanceof Stringable) {
                return (string) $first;
            }
        }

        if (null === $translation) {
            return $id;
        }

        if (is_scalar($translation)) {
            return (string) $translation;
        }

        return $id;
    }

    protected function getGridActions(): GridActionCollectionInterface
    {
        $actions = new GridActionCollection();

        $actions->add(
            (new SimpleGridAction('common_refresh_list'))
                ->setName($this->transString('Refresh list', [], 'Admin.Advparameters.Feature'))
                ->setIcon('refresh')
        );

        return $actions;
    }
}
