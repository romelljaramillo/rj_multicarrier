<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Shipment;

use Address;
use Carrier;
use Context;
use Country;
use Customer;
use Order;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\GenerateShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentGenerationException;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\GenerateShipmentHandler;
use Roanja\Module\RjMulticarrier\Entity\Carrier as CarrierEntity;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Entity\InfoShipment;
use Roanja\Module\RjMulticarrier\Entity\Shipment as ShipmentEntity;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Roanja\Module\RjMulticarrier\Repository\InfoShipmentRepository;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Support\Common;
use State;
use Validate;

final class ShipmentGenerationService
{
    public function __construct(
        private readonly InfoShipmentRepository $infoShipmentRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly ConfigurationRepository $ConfigurationRepository,
        private readonly LegacyConfiguration $legacyConfiguration,
        private readonly GenerateShipmentHandler $generateShipmentHandler
    ) {
    }

    /**
     * @throws ShipmentGenerationException
     */
    public function generateForInfoShipment(int $infoPackageId): ShipmentEntity
    {
        $infoPackage = $this->infoShipmentRepository->find($infoPackageId);

        if (!$infoPackage instanceof InfoShipment) {
            throw ShipmentGenerationException::infoPackageNotFound($infoPackageId);
        }

        $orderId = $infoPackage->getOrderId();
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            throw ShipmentGenerationException::orderNotFound($orderId);
        }

        $context = Context::getContext();
        $shopId = (int) $context->shop->id;
        if ($shopId > 0) {
                $belongsToShop = false;
                foreach ($infoPackage->getShops() as $shopMapping) {
                    if ($shopMapping->getShopId() === $shopId) {
                        $belongsToShop = true;
                        break;
                    }
                }

                if (!$belongsToShop) {
                    throw ShipmentGenerationException::infoPackageNotFound($infoPackageId);
                }
        }

        $existingShipmentId = $this->shipmentRepository->shipmentExistsByInfoShipment($infoPackageId);
        if (null !== $existingShipmentId) {
            throw ShipmentGenerationException::shipmentAlreadyExists($infoPackageId);
        }

        $languageId = (int) $context->language->id;

        $referenceCarrierId = (int) $infoPackage->getReferenceCarrierId();
        if (0 === $referenceCarrierId) {
            $carrier = new Carrier((int) $order->id_carrier, $languageId);
            if (Validate::isLoadedObject($carrier)) {
                $referenceCarrierId = (int) $carrier->id_reference;
            }
        }

        if ($referenceCarrierId <= 0) {
            throw ShipmentGenerationException::carrierNotConfigured($referenceCarrierId);
        }

        $companyData = $this->resolveCompanyData($referenceCarrierId);
        $companyId = (int) ($companyData['id_carrier'] ?? 0);
        if (0 === $companyId) {
            throw ShipmentGenerationException::carrierNotConfigured($referenceCarrierId);
        }

        $typeShipments = $this->resolveTypeShipments($companyId);
        if ([] === $typeShipments) {
            throw ShipmentGenerationException::typeShipmentsMissing($companyId);
        }

        $carrierCode = (string) ($companyData['shortname'] ?? '');

        $ConfigurationPayload = $this->mapConfiguration($languageId);
        $carrierDisplayName = $this->resolveCarrierName($referenceCarrierId, $languageId);
        if ('' === $carrierDisplayName) {
            $carrierDisplayName = (string) ($companyData['name'] ?? '');
        }
        $payload = [
            'id_order' => $orderId,
            'reference' => (string) $order->reference,
            'info_package' => $this->mapInfoShipment($infoPackage, $referenceCarrierId),
            'info_customer' => $this->mapCustomer($order, $languageId),
            'configuration_shop' => $ConfigurationPayload,
            'carriers' => Carrier::getCarriers($languageId, true, false, false, null, false),
            'carrier_name' => $carrierDisplayName,
            'name_carrier' => $carrierDisplayName,
            'info_company_carrier' => $companyData,
            'info_type_shipment' => $typeShipments,
            'config_extra_info' => [
                'RJ_ETIQUETA_TRANSP_PREFIX' => (string) ($ConfigurationPayload['RJ_ETIQUETA_TRANSP_PREFIX'] ?? ''),
                'RJ_MODULE_CONTRAREEMBOLSO' => (string) ($ConfigurationPayload['RJ_MODULE_CONTRAREEMBOLSO'] ?? ''),
            ],
        ];

        $shipmentNumber = Common::getUUID();
        $options = [];
        if ('' !== $carrierCode) {
            $options['shortname'] = $carrierCode;
        }

        $command = new GenerateShipmentCommand(
            '' !== $carrierCode ? $carrierCode : 'DEF',
            $orderId,
            (string) $order->reference,
            $shipmentNumber,
            $payload,
            $options
        );

        return $this->generateShipmentHandler->handle($command);
    }

    /**
     * @param int[] $infoPackageIds
     *
     * @return array{generated: int[], errors: array<int, string>}
     */
    public function generateBulk(array $infoPackageIds): array
    {
        $results = [
            'generated' => [],
            'errors' => [],
        ];

        foreach (array_unique(array_map('intval', $infoPackageIds)) as $infoPackageId) {
            if ($infoPackageId <= 0) {
                continue;
            }

            try {
                $this->generateForInfoShipment($infoPackageId);
                $results['generated'][] = $infoPackageId;
            } catch (ShipmentGenerationException $exception) {
                $results['errors'][$infoPackageId] = $exception->getMessage();
            } catch (\Throwable $throwable) {
                $results['errors'][$infoPackageId] = $throwable->getMessage();
            }
        }

        return $results;
    }

    private function resolveCarrierName(int $referenceId, int $languageId): string
    {
        if ($referenceId <= 0) {
            return '';
        }

        $carrier = Carrier::getCarrierByReference($referenceId, $languageId);

        if (is_object($carrier) && isset($carrier->name)) {
            return (string) $carrier->name;
        }

        if (is_array($carrier) && isset($carrier['name'])) {
            return (string) $carrier['name'];
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapConfiguration(int $languageId): array
    {
        $defaults = [
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
        ];

        $Configuration = $this->findConfigurationForContext();
        if ($Configuration instanceof Configuration) {
            $defaults = array_merge($defaults, $this->mapConfigurationToFormData($Configuration));
        }

        $defaults['isbusiness'] = $this->normalizeBusinessFlag($defaults['isbusiness']);

        $shopId = $this->resolveShopId();
        $extraConfig = $this->getExtraConfiguration($shopId);
        $defaults['RJ_ETIQUETA_TRANSP_PREFIX'] = $extraConfig['RJ_ETIQUETA_TRANSP_PREFIX'];
        $defaults['RJ_MODULE_CONTRAREEMBOLSO'] = $extraConfig['RJ_MODULE_CONTRAREEMBOLSO'];

        $countryId = isset($defaults['id_country']) ? (int) $defaults['id_country'] : 0;
        $countryName = '';
        $countryIso = '';

        if ($countryId > 0) {
            $countryName = (string) Country::getNameById($languageId, $countryId);
            $country = new Country($countryId, $languageId);
            if (Validate::isLoadedObject($country)) {
                $countryIso = (string) $country->iso_code;
            }
        }

        return array_merge($defaults, [
            'id_country' => $countryId,
            'country' => $countryName,
            'country_iso' => $countryIso,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCustomer(Order $order, int $languageId): array
    {
        $address = new Address((int) $order->id_address_delivery, $languageId);
        $customer = new Customer((int) $order->id_customer);
        $country = new Country((int) $address->id_country, $languageId);
        $state = new State((int) $address->id_state);

        return [
            'id_order' => (int) $order->id,
            'firstname' => (string) $address->firstname,
            'lastname' => (string) $address->lastname,
            'company' => (string) $address->company,
            'address1' => (string) $address->address1,
            'address2' => (string) $address->address2,
            'postcode' => (string) $address->postcode,
            'city' => (string) $address->city,
            'state' => Validate::isLoadedObject($state) ? (string) $state->name : '',
            'id_state' => (int) $address->id_state,
            'country' => Validate::isLoadedObject($country) ? (string) $country->name : '',
            'countrycode' => Validate::isLoadedObject($country) ? (string) $country->iso_code : '',
            'id_country' => (int) $address->id_country,
            'phone' => (string) $address->phone,
            'phone_mobile' => (string) $address->phone_mobile,
            'email' => Validate::isLoadedObject($customer) ? (string) $customer->email : '',
            'vat_number' => (string) $address->vat_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInfoShipment(InfoShipment $infoPackage, int $referenceCarrierId): array
    {
        $hourFrom = $infoPackage->getHourFrom();
        $hourUntil = $infoPackage->getHourUntil();

        return [
            'id_info_shipment' => $infoPackage->getId(),
            'id_order' => $infoPackage->getOrderId(),
            'id_reference_carrier' => $referenceCarrierId,
            'id_type_shipment' => $infoPackage->getTypeShipment()->getId(),
            'quantity' => $infoPackage->getQuantity(),
            'weight' => $infoPackage->getWeight(),
            'length' => $infoPackage->getLength(),
            'width' => $infoPackage->getWidth(),
            'height' => $infoPackage->getHeight(),
            'cash_ondelivery' => $this->normalizePriceValue($infoPackage->getCashOnDelivery()),
            'message' => $infoPackage->getMessage(),
            'hour_from' => $hourFrom ? $hourFrom->format('H:i:s') : null,
            'hour_until' => $hourUntil ? $hourUntil->format('H:i:s') : null,
            'retorno' => $infoPackage->getRetorno(),
            'rcs' => $infoPackage->isRcsEnabled() ? 1 : 0,
            'vsec' => $infoPackage->getVsec(),
            'dorig' => $infoPackage->getDorig(),
        ];
    }

    private function normalizePriceValue(mixed $value): float
    {
        if (null === $value) {
            return 0.0;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', $value);

            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCompanyData(int $referenceCarrierId): array
    {
        if ($referenceCarrierId <= 0) {
            return [];
        }

        $typeShipment = $this->typeShipmentRepository->findActiveByReferenceCarrier($referenceCarrierId);
        if ($typeShipment instanceof TypeShipment) {
            return $this->mapCompany($typeShipment->getCarrier());
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveTypeShipments(int $companyId): array
    {
        $typeShipments = $this->typeShipmentRepository->findByCarrierId($companyId, true);

        if ([] === $typeShipments) {
            return [];
        }

        $mapped = [];
        foreach ($typeShipments as $typeShipment) {
            $mapped[] = $this->mapTypeShipment($typeShipment, $typeShipment->getCarrier());
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCompany(CarrierEntity $company): array
    {
        return [
            'id_carrier' => (int) $company->getId(),
            'name' => $company->getName(),
            'shortname' => $company->getShortName(),
            'icon' => $company->getIcon(),
            'date_add' => $company->getCreatedAt()?->format('Y-m-d H:i:s'),
            'date_upd' => $company->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTypeShipment(TypeShipment $typeShipment, CarrierEntity $company): array
    {
        return [
            'id_type_shipment' => (int) $typeShipment->getId(),
            'id_carrier' => (int) $company->getId(),
            'name' => $typeShipment->getName(),
            'id_bc' => $typeShipment->getBusinessCode(),
            'id_reference_carrier' => $typeShipment->getReferenceCarrierId(),
            'active' => $typeShipment->isActive() ? 1 : 0,
            'carrier_company' => $company->getName(),
            'shortname' => $company->getShortName(),
            'date_add' => $typeShipment->getCreatedAt()?->format('Y-m-d H:i:s'),
            'date_upd' => $typeShipment->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function getExtraConfiguration(?int $shopId = null): array
    {
        if (null === $shopId) {
            $shopId = $this->resolveShopId();
        }

        $constraint = $shopId > 0 ? ShopConstraint::shop($shopId) : ShopConstraint::allShops();

        return [
            'RJ_ETIQUETA_TRANSP_PREFIX' => (string) $this->legacyConfiguration->get('RJ_ETIQUETA_TRANSP_PREFIX', '', $constraint),
            'RJ_MODULE_CONTRAREEMBOLSO' => (string) $this->legacyConfiguration->get('RJ_MODULE_CONTRAREEMBOLSO', '', $constraint),
        ];
    }

    private function resolveShopId(): int
    {
        $context = Context::getContext();

        if (isset($context->shop->id)) {
            return (int) $context->shop->id;
        }

        return 0;
    }

    private function findConfigurationForContext(): ?Configuration
    {
        $shopId = $this->resolveShopId();

        if ($shopId > 0) {
            $Configuration = $this->ConfigurationRepository->findOneByShop($shopId);
            if ($Configuration instanceof Configuration) {
                return $Configuration;
            }
        }

        return $this->ConfigurationRepository->findFirst();
    }

    /**
     * @return array<string, mixed>
     */
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
}
