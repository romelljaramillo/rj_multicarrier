<?php
/**
 * Proveedor de opciones para formularios y filtros de reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form\ValidationRule;

use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

if (!class_exists('\Shop') && class_exists('\ShopCore')) {
    class_alias('\ShopCore', '\Shop');
}

if (!class_exists('\ShopGroup') && class_exists('\ShopGroupCore')) {
    class_alias('\ShopGroupCore', '\ShopGroup');
}

if (!class_exists('\Product') && class_exists('\ProductCore')) {
    class_alias('\ProductCore', '\Product');
}

if (!class_exists('\Category') && class_exists('\CategoryCore')) {
    class_alias('\CategoryCore', '\Category');
}

if (!class_exists('\Zone') && class_exists('\ZoneCore')) {
    class_alias('\ZoneCore', '\Zone');
}

if (!class_exists('\Country') && class_exists('\CountryCore')) {
    class_alias('\CountryCore', '\Country');
}

if (!class_exists('\Context') && class_exists('\ContextCore')) {
    class_alias('\ContextCore', '\Context');
}

if (!class_exists('\Carrier') && class_exists('\CarrierCore')) {
    class_alias('\CarrierCore', '\Carrier');
}

if (!class_exists('\Db') && class_exists('\DbCore')) {
    class_alias('\DbCore', '\Db');
}

final class ValidationRuleFormOptionsProvider
{
    public function __construct(private readonly CarrierRepository $carrierRepository)
    {
    }

    /**
     * @return array<string, string>
     */
    public function getScopeChoices(): array
    {
        $choices = [
            'Todos los contextos' => 'global',
        ];

        $groupChoices = [];

        if (class_exists('\ShopGroup')) {
            $groups = \ShopGroup::getShopGroups(false);
            $groupItems = $groups instanceof \Traversable ? iterator_to_array($groups, false) : (array) $groups;

            foreach ($groupItems as $group) {
                if (is_array($group)) {
                    $groupId = (int) ($group['id_shop_group'] ?? $group['id'] ?? 0);
                    $groupName = (string) ($group['name'] ?? ('ID ' . $groupId));
                } elseif (is_object($group)) {
                    $groupId = (int) ($group->id ?? $group->id_shop_group ?? 0);
                    $groupName = (string) ($group->name ?? ('ID ' . $groupId));
                } else {
                    continue;
                }

                if ($groupId <= 0) {
                    continue;
                }

                $label = sprintf('Grupo: %s', $groupName);
                $groupChoices[$label] = sprintf('group-%d', $groupId);
            }
        }

        $choices = array_merge($choices, $groupChoices);

        if (class_exists('\Shop')) {
            $shops = (array) \Shop::getShops(false, null, true);

            foreach ($shops as $shopId => $shopName) {
                $shopId = (int) $shopId;
                if ($shopId <= 0) {
                    continue;
                }

                $name = is_string($shopName) ? $shopName : ('ID ' . $shopId);
                $label = sprintf('Tienda: %s', $name);
                $choices[$label] = sprintf('shop-%d', $shopId);
            }
        }

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    public function getCarrierChoices(): array
    {
        $choices = [];

        if (class_exists('\Db')) {
            $sql = sprintf(
                'SELECT c.id_carrier, c.name FROM `%scarrier` c WHERE c.deleted = 0 ORDER BY c.name ASC, c.id_carrier ASC',
                _DB_PREFIX_
            );

            $rows = \Db::getInstance()->executeS($sql) ?: [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $id = (int) ($row['id_carrier'] ?? 0);
                $name = trim((string) ($row['name'] ?? ''));

                if ($id <= 0) {
                    continue;
                }

                $label = $name === '' ? sprintf('Transportista ID %d', $id) : sprintf('%s (ID %d)', $name, $id);
                $choices[$label] = $id;
            }
        }

        if (empty($choices)) {
            foreach ($this->carrierRepository->findAllOrdered() as $carrier) {
                if (null === $carrier->getId()) {
                    continue;
                }

                $label = sprintf('%s (ID %d)', $carrier->getName(), $carrier->getId());
                $choices[$label] = (int) $carrier->getId();
            }
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    public function getProductChoices(): array
    {
        $choices = [];
        $languageId = $this->getContextLanguageId();
        $products = [];

        if (class_exists('\Product')) {
            $products = (array) \Product::getSimpleProducts($languageId);
        }

        foreach ($products as $product) {
            if (is_array($product)) {
                $id = (int) ($product['id_product'] ?? 0);
                $name = (string) ($product['name'] ?? ('ID ' . $id));
            } elseif (is_object($product)) {
                $id = (int) ($product->id ?? $product->id_product ?? 0);
                $name = (string) ($product->name ?? ('ID ' . $id));
            } else {
                continue;
            }

            if ($id <= 0) {
                continue;
            }

            $label = sprintf('%s (ID %d)', $name, $id);
            $choices[$label] = $id;
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    public function getCategoryChoices(): array
    {
        $choices = [];
        $languageId = $this->getContextLanguageId();
        $categories = [];

        if (class_exists('\Category')) {
            $categories = (array) \Category::getSimpleCategories($languageId);
        }

        foreach ($categories as $category) {
            if (is_array($category)) {
                $id = (int) ($category['id_category'] ?? 0);
                $name = (string) ($category['name'] ?? ('ID ' . $id));
            } elseif (is_object($category)) {
                $id = (int) ($category->id ?? $category->id_category ?? 0);
                $name = (string) ($category->name ?? ('ID ' . $id));
            } else {
                continue;
            }

            if ($id <= 0) {
                continue;
            }

            $label = sprintf('%s (ID %d)', $name, $id);
            $choices[$label] = $id;
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    public function getZoneChoices(): array
    {
        $choices = [];
        $zones = [];

        if (class_exists('\Zone')) {
            $zones = (array) \Zone::getZones(true);
        }

        foreach ($zones as $zone) {
            if (is_array($zone)) {
                $id = (int) ($zone['id_zone'] ?? 0);
                $name = (string) ($zone['name'] ?? ('ID ' . $id));
            } elseif (is_object($zone)) {
                $id = (int) ($zone->id ?? $zone->id_zone ?? 0);
                $name = (string) ($zone->name ?? ('ID ' . $id));
            } else {
                continue;
            }

            if ($id <= 0) {
                continue;
            }

            $label = sprintf('%s (ID %d)', $name, $id);
            $choices[$label] = $id;
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    public function getCountryChoices(): array
    {
        $choices = [];
        $languageId = $this->getContextLanguageId();
        $countries = [];

        if (class_exists('\Country')) {
            $countries = (array) \Country::getCountries($languageId, true);
        }

        foreach ($countries as $country) {
            if (is_array($country)) {
                $id = (int) ($country['id_country'] ?? 0);
                $name = (string) ($country['name'] ?? ('ID ' . $id));
            } elseif (is_object($country)) {
                $id = (int) ($country->id ?? $country->id_country ?? 0);
                $name = (string) ($country->name ?? ('ID ' . $id));
            } else {
                continue;
            }

            if ($id <= 0) {
                continue;
            }

            $label = sprintf('%s (ID %d)', $name, $id);
            $choices[$label] = $id;
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        return $choices;
    }

    private function getContextLanguageId(): int
    {
        if (class_exists('\Context')) {
            $context = \Context::getContext();

            if (isset($context->language->id)) {
                return (int) $context->language->id;
            }
        }

        return 1;
    }
}
