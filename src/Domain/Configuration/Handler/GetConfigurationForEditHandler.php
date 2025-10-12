<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\ConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Query\GetConfigurationForEdit;
use Roanja\Module\RjMulticarrier\Domain\Configuration\View\ConfigurationDetailView;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;

final class GetConfigurationForEditHandler
{
    public function __construct(private readonly ConfigurationRepository $ConfigurationRepository)
    {
    }

    public function handle(GetConfigurationForEdit $query): ConfigurationDetailView
    {
        $Configuration = $this->ConfigurationRepository->find($query->getConfigurationId());

        if (!$Configuration instanceof Configuration) {
            throw ConfigurationNotFoundException::forId($query->getConfigurationId());
        }

        return $this->mapToView($Configuration);
    }

    private function mapToView(Configuration $Configuration): ConfigurationDetailView
    {
        $shops = [];
        foreach ($Configuration->getShops() as $mapping) {
            if (method_exists($mapping, 'getShopId')) {
                $shops[] = (int) $mapping->getShopId();
            }
        }

        $createdAt = $Configuration->getCreatedAt();
        $updatedAt = $Configuration->getUpdatedAt();

        $labelPrefix = $Configuration->getLabelPrefix();
        $cashOnDeliveryModule = $Configuration->getCashOnDeliveryModule();
        $primaryShopId = $shops[0] ?? null;

        if ((null === $labelPrefix || '' === trim((string) $labelPrefix)) && class_exists('\Configuration') && null !== $primaryShopId) {
            $labelPrefix = (string) call_user_func(['\Configuration', 'get'], 'RJ_ETIQUETA_TRANSP_PREFIX', null, null, $primaryShopId);
        }

        if ((null === $cashOnDeliveryModule || '' === trim((string) $cashOnDeliveryModule)) && class_exists('\Configuration') && null !== $primaryShopId) {
            $cashOnDeliveryModule = (string) call_user_func(['\Configuration', 'get'], 'RJ_MODULE_CONTRAREEMBOLSO', null, null, $primaryShopId);
        }

        return new ConfigurationDetailView(
            $Configuration->getId() ?? 0,
            $Configuration->getFirstName(),
            $Configuration->getLastName(),
            $Configuration->getCompany(),
            $Configuration->getAdditionalName(),
            $Configuration->getCountryId(),
            $Configuration->getState(),
            $Configuration->getCity(),
            $Configuration->getStreet(),
            $Configuration->getStreetNumber(),
            $Configuration->getPostcode(),
            $Configuration->getAdditionalAddress(),
            $this->normalizeBusinessFlag($Configuration->getIsBusinessFlag()),
            $Configuration->getEmail(),
            $Configuration->getPhone(),
            $Configuration->getVatNumber(),
            $labelPrefix,
            $cashOnDeliveryModule,
            $shops,
            $createdAt ? $createdAt->format('Y-m-d H:i:s') : null,
            $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null,
            $Configuration->isActive()
        );
    }

    private function normalizeBusinessFlag(?string $flag): ?bool
    {
        if (null === $flag || '' === trim($flag)) {
            return null;
        }

        return in_array(trim($flag), ['1', 'true', 'on', 'yes'], true);
    }
}
