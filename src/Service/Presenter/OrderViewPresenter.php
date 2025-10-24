<?php
/**
 * Provides data and rendering helpers for the admin order view integration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Presenter;

use Context;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query\GetCarrierConfigurationsForCarrier;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\View\CarrierConfigurationView;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\GetShipmentByOrderIdHandler;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentByOrderId;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query\GetTypeShipmentConfigurations;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\View\TypeShipmentConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentView;
use Roanja\Module\RjMulticarrier\Entity\Carrier as CarrierEntity;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Shop;
use Twig\Environment;

final class OrderViewPresenter
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GetShipmentByOrderIdHandler $getShipmentByOrderIdHandler,
        private readonly ?InfoPackageRepository $infoPackageRepository = null,
        private readonly ?CarrierRepository $carrierRepository = null,
        private readonly ?TypeShipmentRepository $typeShipmentRepository = null,
        private readonly ?ConfigurationRepository $configurationRepository = null,
        private readonly ?CommandBusInterface $queryBus = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function present(int $orderId, array $context = []): string
    {
        if (0 === $orderId) {
            return '';
        }

        $notifications = $context['notifications'] ?? [];
        $notifications = array_merge(
            [
                'success' => [],
                'errors' => [],
                'warning' => [],
                'info' => [],
            ],
            array_intersect_key($notifications, array_flip(['success', 'errors', 'warning', 'info']))
        );

        $submittedValues = [];
        if (isset($context['submitted']) && is_array($context['submitted'])) {
            $submittedValues = $context['submitted'];
        }

        $packageOverride = null;
        if (isset($context['package']) && is_array($context['package'])) {
            $packageOverride = $context['package'];
        }

        $shipmentView = $this->getShipmentByOrderIdHandler->handle(new GetShipmentByOrderId($orderId));

        $order = null;
        try {
            $candidateOrder = new \Order($orderId);
            if (\Validate::isLoadedObject($candidateOrder)) {
                $order = $candidateOrder;
            }
        } catch (\Throwable) {
            $order = null;
        }

        // Prepare legacy-compatible variables so the Twig template can render a similar form
        $infoPackage = [];
        $infoShipment = null;
        $labels = [];
        $carriers = [];
        $carrierName = '';

        // Normalize shipment to an array so Twig deals with consistent structure.
        $shipmentArray = null;
        if ($shipmentView instanceof ShipmentView) {
            $shipmentArray = $shipmentView->toArray();
        } elseif (is_array($shipmentView)) {
            $shipmentArray = $shipmentView;
        }

        if ($shipmentArray instanceof ShipmentView) {
            $shipmentArray = $shipmentArray->toArray();
        }

        if (is_array($shipmentArray)) {
            $infoPackage = $shipmentArray['package'] ?? [];
            $labels = $shipmentArray['labels'] ?? [];
            $carrierName = $shipmentArray['carrierShortName'] ?? '';
            $infoShipment = ['id_shipment' => $shipmentArray['id'] ?? null];
        }

        if (is_array($packageOverride) && !empty($packageOverride)) {
            $infoPackage = array_merge($infoPackage, $packageOverride);
        }

        if ((empty($infoPackage) || !isset($infoPackage['id_infopackage'])) && $this->infoPackageRepository instanceof InfoPackageRepository) {
            try {
                $shopId = Shop::getContextShopID();
                if ($shopId <= 0) {
                    $shopId = (int) (Context::getContext()->shop->id ?? 0);
                }
                if ($shopId > 0) {
                    $packageRow = $this->infoPackageRepository->getPackageByOrder($orderId, $shopId);
                    if (is_array($packageRow)) {
                        $infoPackage = array_merge($infoPackage, $packageRow);
                    }
                }
            } catch (\Throwable) {
                // swallow to keep rendering resilient
            }
        }

        // Modern presenter: do not rely on legacy Context/Carrier classes.
        $urlAjax = '';

        $selectedTypeShipmentId = isset($infoPackage['id_type_shipment']) ? (int) $infoPackage['id_type_shipment'] : null;
        $selectedCarrierId = isset($infoPackage['id_carrier']) ? (int) $infoPackage['id_carrier'] : null;

        // Load companies and default type shipments for the form (modern repositories)
        $companiesData = [];
        $typeShipmentsData = [];
        $companyEntities = [];

        try {
            if ($this->carrierRepository instanceof CarrierRepository) {
                $companyEntities = $this->carrierRepository->findAllOrdered();
            }
        } catch (\Throwable) {
            $companyEntities = [];
        }

        foreach ($companyEntities as $companyEntity) {
            if (!$companyEntity instanceof CarrierEntity) {
                continue;
            }

            $companiesData[] = [
                'id' => $companyEntity->getId(),
                'name' => $companyEntity->getName(),
                'shortname' => $companyEntity->getShortName(),
            ];

            try {
                if ($this->typeShipmentRepository instanceof TypeShipmentRepository) {
                    $types = $this->typeShipmentRepository->findByCarrier($companyEntity, true);
                } else {
                    $types = [];
                }
            } catch (\Throwable) {
                $types = [];
            }

            foreach ($types as $typeEntity) {
                if (!$typeEntity instanceof TypeShipment) {
                    continue;
                }

                $typeShipmentsData[] = [
                    'id' => $typeEntity->getId(),
                    'name' => $typeEntity->getName(),
                    'carrier_id' => $companyEntity->getId(),
                    'reference_carrier_id' => $typeEntity->getReferenceCarrierId(),
                    'business_code' => $typeEntity->getBusinessCode(),
                ];
            }
        }

        if (null === $selectedCarrierId && $selectedTypeShipmentId && $this->typeShipmentRepository instanceof TypeShipmentRepository) {
            try {
                $typeShipmentEntity = $this->typeShipmentRepository->findOneById($selectedTypeShipmentId);
                if ($typeShipmentEntity instanceof TypeShipment) {
                    $selectedCarrierId = $typeShipmentEntity->getCarrier()->getId();
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if (null === $selectedCarrierId && !empty($companiesData)) {
            $selectedCarrierId = (int) ($companiesData[0]['id'] ?? 0);
        }

        // Load companies and default type shipments for the form (modern repositories)
        $configurations = $this->buildConfigurationPayload($shipmentArray);

        $languageId = (int) Context::getContext()->language->id;
        $shopConfiguration = $this->resolveShopConfiguration();
        $shopInfo = $this->buildShopInfo($shopConfiguration, $languageId);
        $customerInfo = $this->buildCustomerInfo($order, $languageId);
        $currencyData = $this->resolveOrderCurrency($order);

        $previewCarrierName = $carrierName;
        if (!$previewCarrierName && $selectedCarrierId) {
            foreach ($companiesData as $companyRow) {
                if ((int) $companyRow['id'] === (int) $selectedCarrierId) {
                    $previewCarrierName = (string) ($companyRow['name'] ?? '');
                    break;
                }
            }
        }

        return $this->twig->render('@Modules/rj_multicarrier/views/templates/admin/order/panel.html.twig', [
            'orderId' => $orderId,
            'shipment' => $shipmentArray,
            'info_package' => $infoPackage,
            'info_shipment' => $infoShipment,
            'labels' => $labels,
            'carrier_name' => $previewCarrierName,
            'id_order' => $orderId,
            'url_ajax' => $urlAjax,
            'config_extra_info' => $configurations,
            'info_customer' => $customerInfo,
            'configuration_shop' => $shopInfo,
            // modern lists for form rendering when no shipment exists
            'companies' => $companiesData,
            'type_shipments' => $typeShipmentsData,
            'selected_carrier_id' => $selectedCarrierId,
            'selected_type_shipment_id' => $selectedTypeShipmentId,
            'notifications' => $notifications,
            'request_values' => $submittedValues,
            'order_currency' => $currencyData,
        ]);
    }

    /**
     * @param array<string, mixed>|null $shipment
     *
     * @return array<string, array<string, string|null>>
     */
    private function buildConfigurationPayload(?array $shipment): array
    {
        if (null === $this->queryBus) {
            return [
                'company' => [],
                'type_shipment' => [],
            ];
        }

        $carrierId = (int) ($shipment['carrierId'] ?? $shipment['companyId'] ?? 0);
        $typeShipmentId = (int) ($shipment['typeShipmentId'] ?? $shipment['id_type_shipment'] ?? 0);

        $companyConfig = [];
        $typeConfig = [];

        if ($carrierId > 0) {
            try {
                /** @var CarrierConfigurationView[] $views */
                $views = $this->queryBus->handle(new GetCarrierConfigurationsForCarrier($carrierId));
                foreach ($views as $view) {
                    $companyConfig[$view->getName()] = $view->getValue();
                }
            } catch (\Throwable) {
                $companyConfig = [];
            }
        }

        if ($typeShipmentId > 0) {
            try {
                /** @var TypeShipmentConfigurationView[] $views */
                $views = $this->queryBus->handle(new GetTypeShipmentConfigurations($typeShipmentId));
                foreach ($views as $view) {
                    $typeConfig[$view->getName()] = $view->getValue();
                }
            } catch (\Throwable) {
                $typeConfig = [];
            }
        }

        return [
            'company' => $companyConfig,
            'type_shipment' => $typeConfig,
        ];
    }

    private function resolveShopConfiguration(): ?Configuration
    {
        if (!$this->configurationRepository instanceof ConfigurationRepository) {
            return null;
        }

        $shopId = Shop::getContextShopID();
        if ($shopId <= 0) {
            $shopId = (int) (Context::getContext()->shop->id ?? 0);
        }

        if ($shopId > 0) {
            $configuration = $this->configurationRepository->findOneByShop($shopId);
            if ($configuration instanceof Configuration) {
                return $configuration;
            }
        }

        return $this->configurationRepository->findFirst();
    }

    private function buildShopInfo(?Configuration $configuration, int $languageId): array
    {
        if (!$configuration instanceof Configuration) {
            return [];
        }

        $countryName = '';
        $countryIso = '';

        try {
            $countryId = $configuration->getCountryId();
            $countryIso = \Country::getIsoById((int) $countryId) ?: '';
            $countryName = \Country::getNameById($languageId, (int) $countryId) ?: '';
        } catch (\Throwable) {
            $countryName = '';
            $countryIso = '';
        }

        return [
            'firstname' => $configuration->getFirstName(),
            'lastname' => $configuration->getLastName(),
            'company' => $configuration->getCompany() ?: trim($configuration->getFirstName() . ' ' . $configuration->getLastName()),
            'additionalname' => $configuration->getAdditionalName(),
            'street' => trim($configuration->getStreet() . ' ' . $configuration->getStreetNumber()),
            'city' => $configuration->getCity(),
            'state' => $configuration->getState(),
            'postcode' => $configuration->getPostcode(),
            'additionaladdress' => $configuration->getAdditionalAddress(),
            'country' => $countryName,
            'countrycode' => $countryIso,
            'phone' => $configuration->getPhone(),
            'email' => $configuration->getEmail(),
            'vatnumber' => $configuration->getVatNumber(),
        ];
    }

    private function buildCustomerInfo(?\Order $order, int $languageId): array
    {
        if (!$order instanceof \Order || !\Validate::isLoadedObject($order)) {
            return [];
        }

        try {
            $address = new \Address((int) $order->id_address_delivery);
            if (!\Validate::isLoadedObject($address)) {
                return [];
            }

            $countryIso = '';
            $countryName = '';
            if ($address->id_country) {
                $countryIso = \Country::getIsoById((int) $address->id_country) ?: '';
                $countryName = \Country::getNameById($languageId, (int) $address->id_country) ?: '';
            }

            $stateName = '';
            if ($address->id_state) {
                $stateName = \State::getNameById((int) $address->id_state) ?: '';
            }

            $customer = new \Customer((int) $order->id_customer);

            return [
                'firstname' => (string) $address->firstname,
                'lastname' => (string) $address->lastname,
                'company' => (string) $address->company,
                'address1' => trim($address->address1 . ' ' . $address->address2),
                'postcode' => (string) $address->postcode,
                'city' => (string) $address->city,
                'state' => $stateName,
                'country' => $countryName,
                'countrycode' => $countryIso,
                'phone' => (string) $address->phone,
                'phone_mobile' => (string) $address->phone_mobile,
                'email' => \Validate::isLoadedObject($customer) ? (string) $customer->email : '',
                'vat_number' => (string) $address->vat_number,
                'dni' => (string) $address->dni,
                'other' => (string) $address->other,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string|float>
     */
    private function resolveOrderCurrency(?\Order $order): array
    {
        if (!$order instanceof \Order || !$order->id_currency) {
            return [];
        }

        try {
            $currency = \Currency::getCurrencyInstance((int) $order->id_currency);
            return [
                'symbol' => $currency->sign ?? '',
                'iso_code' => $currency->iso_code ?? '',
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
