<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Query\GetConfigurationForContext;
use Roanja\Module\RjMulticarrier\Domain\Configuration\View\ConfigurationView;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;

final class GetConfigurationForContextHandler
{
    public function __construct(
        private readonly ConfigurationRepository $ConfigurationRepository,
        private readonly ShopContext $shopContext
    )
    {
    }

    public function handle(GetConfigurationForContext $query): ConfigurationView
    {
        $contextShopIds = $this->getContextShopIds();
        $shopId = $contextShopIds[0] ?? 0;

        $Configuration = null;
        if ($shopId > 0) {
            $Configuration = $this->ConfigurationRepository->findOneByShop($shopId);
        }

        if (!$Configuration instanceof Configuration) {
            $Configuration = $this->ConfigurationRepository->findFirst();
        }

        $formData = $this->buildDefaults();
        $formData['shop_association'] = $contextShopIds;

        if ($Configuration instanceof Configuration) {
            $formData = array_replace($formData, $this->mapConfigurationToFormData($Configuration));

            $association = $this->extractShopIds($Configuration);
            if (!empty($association)) {
                $formData['shop_association'] = $association;
                $shopId = $association[0] ?? $shopId;
            }
        }

        $formData['isbusiness'] = $this->normalizeBusinessFlag($formData['isbusiness']);

        if ('' === ($formData['RJ_ETIQUETA_TRANSP_PREFIX'] ?? '') && class_exists('\Configuration')) {
            $formData['RJ_ETIQUETA_TRANSP_PREFIX'] = (string) call_user_func(['\Configuration', 'get'], 'RJ_ETIQUETA_TRANSP_PREFIX', null, null, $shopId);
        }

        if ('' === ($formData['RJ_MODULE_CONTRAREEMBOLSO'] ?? '') && class_exists('\Configuration')) {
            $formData['RJ_MODULE_CONTRAREEMBOLSO'] = (string) call_user_func(['\Configuration', 'get'], 'RJ_MODULE_CONTRAREEMBOLSO', null, null, $shopId);
        }

        if (null === $formData['id_configuration'] && $Configuration instanceof Configuration) {
            $formData['id_configuration'] = $Configuration->getId();
        }

        return new ConfigurationView($shopId, $formData);
    }

    private function buildDefaults(): array
    {
        return [
            'id_configuration' => null,
            'firstname' => '',
            'lastname' => '',
            'company' => null,
            'additionalname' => null,
            'id_country' => null,
            'state' => '',
            'city' => '',
            'street' => '',
            'number' => '',
            'postcode' => '',
            'additionaladdress' => null,
            'isbusiness' => false,
            'email' => null,
            'phone' => '',
            'vatnumber' => null,
            'RJ_ETIQUETA_TRANSP_PREFIX' => '',
            'RJ_MODULE_CONTRAREEMBOLSO' => '',
            'shop_association' => [],
        ];
    }

    private function mapConfigurationToFormData(Configuration $Configuration): array
    {
        return [
            'id_configuration' => $Configuration->getId(),
            'firstname' => $Configuration->getFirstName(),
            'lastname' => $Configuration->getLastName(),
            'company' => $Configuration->getCompany(),
            'additionalname' => $Configuration->getAdditionalName(),
            'id_country' => $Configuration->getCountryId(),
            'state' => $Configuration->getState(),
            'city' => $Configuration->getCity(),
            'street' => $Configuration->getStreet(),
            'number' => $Configuration->getStreetNumber(),
            'postcode' => $Configuration->getPostcode(),
            'additionaladdress' => $Configuration->getAdditionalAddress(),
            'isbusiness' => $Configuration->getIsBusinessFlag(),
            'email' => $Configuration->getEmail(),
            'phone' => $Configuration->getPhone(),
            'vatnumber' => $Configuration->getVatNumber(),
            'RJ_ETIQUETA_TRANSP_PREFIX' => $Configuration->getLabelPrefix(),
            'RJ_MODULE_CONTRAREEMBOLSO' => $Configuration->getCashOnDeliveryModule(),
            'shop_association' => $this->extractShopIds($Configuration),
        ];
    }

    private function normalizeBusinessFlag(mixed $flag): bool
    {
        if (is_bool($flag)) {
            return $flag;
        }

        if (is_string($flag)) {
            $normalized = filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null !== $normalized) {
                return $normalized;
            }

            return in_array(strtolower($flag), ['1', 'true', 'on', 'yes'], true);
        }

        if (is_int($flag)) {
            return $flag > 0;
        }

        return false;
    }

    /**
     * @return int[]
     */
    private function getContextShopIds(): array
    {
        $shopIds = $this->shopContext->getContextListShopID();

        if (empty($shopIds)) {
            $contextShopId = $this->shopContext->getContextShopID(true);

            if (null !== $contextShopId) {
                $shopIds = [$contextShopId];
            }
        }

        if (empty($shopIds) && class_exists('\Context')) {
            $legacyContext = call_user_func(['\Context', 'getContext']);
            if (isset($legacyContext->shop->id) && (int) $legacyContext->shop->id > 0) {
                $shopIds = [(int) $legacyContext->shop->id];
            }
        }

        return array_values(array_unique(array_map('intval', $shopIds)));
    }

    /**
     * @return int[]
     */
    private function extractShopIds(Configuration $Configuration): array
    {
        $shopIds = [];

        foreach ($Configuration->getShops() as $mapping) {
            if (method_exists($mapping, 'getShopId')) {
                $shopIds[] = (int) $mapping->getShopId();
            }
        }

        return array_values(array_unique($shopIds));
    }
}
