<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler;

use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Query\GetInfoShopForContext;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\View\InfoShopView;
use Roanja\Module\RjMulticarrier\Entity\InfoShop;
use Roanja\Module\RjMulticarrier\Repository\InfoShopRepository;

final class GetInfoShopForContextHandler
{
    public function __construct(
        private readonly InfoShopRepository $infoShopRepository,
        private readonly ShopContext $shopContext
    )
    {
    }

    public function handle(GetInfoShopForContext $query): InfoShopView
    {
        $contextShopIds = $this->getContextShopIds();
        $shopId = $contextShopIds[0] ?? 0;

        $infoShop = null;
        if ($shopId > 0) {
            $infoShop = $this->infoShopRepository->findOneByShop($shopId);
        }

        if (!$infoShop instanceof InfoShop) {
            $infoShop = $this->infoShopRepository->findFirst();
        }

        $formData = $this->buildDefaults();
        $formData['shop_association'] = $contextShopIds;

        if ($infoShop instanceof InfoShop) {
            $formData = array_replace($formData, $this->mapInfoShopToFormData($infoShop));

            $association = $this->extractShopIds($infoShop);
            if (!empty($association)) {
                $formData['shop_association'] = $association;
                $shopId = $association[0] ?? $shopId;
            }
        }

        $formData['isbusiness'] = $this->normalizeBusinessFlag($formData['isbusiness']);

        if (null === $formData['id_infoshop'] && $infoShop instanceof InfoShop) {
            $formData['id_infoshop'] = $infoShop->getId();
        }

        return new InfoShopView($shopId, $formData);
    }

    private function buildDefaults(): array
    {
        return [
            'id_infoshop' => null,
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
            'shop_association' => [],
        ];
    }

    private function mapInfoShopToFormData(InfoShop $infoShop): array
    {
        return [
            'id_infoshop' => $infoShop->getId(),
            'firstname' => $infoShop->getFirstName(),
            'lastname' => $infoShop->getLastName(),
            'company' => $infoShop->getCompany(),
            'additionalname' => $infoShop->getAdditionalName(),
            'id_country' => $infoShop->getCountryId(),
            'state' => $infoShop->getState(),
            'city' => $infoShop->getCity(),
            'street' => $infoShop->getStreet(),
            'number' => $infoShop->getStreetNumber(),
            'postcode' => $infoShop->getPostcode(),
            'additionaladdress' => $infoShop->getAdditionalAddress(),
            'isbusiness' => $infoShop->getIsBusinessFlag(),
            'email' => $infoShop->getEmail(),
            'phone' => $infoShop->getPhone(),
            'vatnumber' => $infoShop->getVatNumber(),
            'shop_association' => $this->extractShopIds($infoShop),
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
    private function extractShopIds(InfoShop $infoShop): array
    {
        $shopIds = [];

        foreach ($infoShop->getShops() as $mapping) {
            if (method_exists($mapping, 'getShopId')) {
                $shopIds[] = (int) $mapping->getShopId();
            }
        }

        return array_values(array_unique($shopIds));
    }
}
