<?php
/**
 * Shared configuration helpers for legacy and Symfony contexts.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Configuration;

use Context;
use Module;
use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use PrestaShop\PrestaShop\Adapter\Country\CountryDataProvider;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\UpsertInfoShop;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler\UpsertInfoShopHandler;
use Roanja\Module\RjMulticarrier\Entity\InfoShop;
use Roanja\Module\RjMulticarrier\Repository\InfoShopRepository;

final class ConfigurationManager
{
    public function __construct(
        private readonly UpsertInfoShopHandler $upsertInfoShopHandler,
        private readonly InfoShopRepository $infoShopRepository,
        private readonly LegacyConfiguration $configuration,
        private readonly CountryDataProvider $countryDataProvider,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfoShopDefaults(): array
    {
        $defaults = [
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
        ];

        $infoShop = $this->findInfoShopForContext();
        if ($infoShop instanceof InfoShop) {
            $defaults = array_merge($defaults, $this->mapInfoShopToFormData($infoShop));
        }

        $defaults['isbusiness'] = $this->normalizeBusinessFlag($defaults['isbusiness']);

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraConfigDefaults(): array
    {
        return [
            'RJ_ETIQUETA_TRANSP_PREFIX' => (string) $this->configuration->get('RJ_ETIQUETA_TRANSP_PREFIX', ''),
            'RJ_MODULE_CONTRAREEMBOLSO' => (string) $this->configuration->get('RJ_MODULE_CONTRAREEMBOLSO', ''),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getCountryChoices(): array
    {
        $context = Context::getContext();
        $languageId = isset($context->language) ? (int) $context->language->id : 1;
        $countries = $this->countryDataProvider->getCountries($languageId, true);

        $choices = [];
        foreach ($countries as $country) {
            $choices[$country['name']] = (int) $country['id_country'];
        }

        ksort($choices);

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    public function getCashOnDeliveryModuleChoices(?string $currentModule): array
    {
        $modules = Module::getPaymentModules();
        $choices = [];

        foreach ($modules as $module) {
            $name = isset($module['name']) ? (string) $module['name'] : '';
            if ('' === $name) {
                continue;
            }

            $displayName = isset($module['displayName']) ? (string) $module['displayName'] : $name;
            $choices[$displayName] = $name;
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        if (null !== $currentModule && '' !== $currentModule && !in_array($currentModule, $choices, true)) {
            $choices[$currentModule] = $currentModule;
        }

        return $choices;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveInfoShop(array $data): void
    {
        $shopId = $this->resolveShopId();

        if (0 === $shopId) {
            throw new \RuntimeException('No se pudo determinar la tienda en contexto.');
        }

        $command = new UpsertInfoShop(
            isset($data['id_infoshop']) && '' !== $data['id_infoshop'] ? (int) $data['id_infoshop'] : null,
            (string) $data['firstname'],
            (string) $data['lastname'],
            $this->nullableString($data['company'] ?? null),
            $this->nullableString($data['additionalname'] ?? null),
            (int) $data['id_country'],
            (string) $data['state'],
            (string) $data['city'],
            (string) $data['street'],
            (string) $data['number'],
            (string) $data['postcode'],
            $this->nullableString($data['additionaladdress'] ?? null),
            isset($data['isbusiness']) ? (bool) $data['isbusiness'] : null,
            $this->nullableString($data['email'] ?? null),
            (string) $data['phone'],
            $this->nullableString($data['vatnumber'] ?? null),
            $shopId
        );

        $this->upsertInfoShopHandler->handle($command);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveExtraConfig(array $data): void
    {
        $shopId = $this->resolveShopId();

        if (0 === $shopId) {
            throw new \RuntimeException('No se pudo determinar la tienda en contexto.');
        }

        $prefix = isset($data['RJ_ETIQUETA_TRANSP_PREFIX']) ? trim((string) $data['RJ_ETIQUETA_TRANSP_PREFIX']) : '';
        $module = isset($data['RJ_MODULE_CONTRAREEMBOLSO']) ? (string) $data['RJ_MODULE_CONTRAREEMBOLSO'] : '';

        $constraint = ShopConstraint::shop($shopId);

        $this->configuration->set('RJ_ETIQUETA_TRANSP_PREFIX', $prefix, $constraint);
        $this->configuration->set('RJ_MODULE_CONTRAREEMBOLSO', $module, $constraint);
    }

    public function getLegacyConfigurationUrl(): string
    {
        $context = Context::getContext();

        if (isset($context->link)) {
            return $context->link->getAdminLink('AdminModules', true, [], ['configure' => 'rj_multicarrier']);
        }

        return 'index.php?controller=AdminModules&configure=rj_multicarrier';
    }

    private function nullableString($value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
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

    private function resolveShopId(): int
    {
        $context = Context::getContext();

        if (isset($context->shop) && $context->shop->id) {
            return (int) $context->shop->id;
        }

        return 0;
    }

    private function findInfoShopForContext(): ?InfoShop
    {
        $shopId = $this->resolveShopId();

        if ($shopId > 0) {
            $infoShop = $this->infoShopRepository->findOneByShop($shopId);
            if ($infoShop instanceof InfoShop) {
                return $infoShop;
            }
        }

        return $this->infoShopRepository->findFirst();
    }

    /**
     * @return array<string, mixed>
     */
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
        ];
    }
}
