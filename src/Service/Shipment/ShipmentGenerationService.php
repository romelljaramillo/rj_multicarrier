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
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage;
use Roanja\Module\RjMulticarrier\Entity\Shipment as ShipmentEntity;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Service\Configuration\ConfigurationManager;
use Roanja\Module\RjMulticarrier\Support\Common;
use State;
use Validate;

final class ShipmentGenerationService
{
    public function __construct(
        private readonly InfoPackageRepository $infoPackageRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly ConfigurationManager $configurationManager,
        private readonly GenerateShipmentHandler $generateShipmentHandler
    ) {
    }

    /**
     * @throws ShipmentGenerationException
     */
    public function generateForInfoPackage(int $infoPackageId): ShipmentEntity
    {
        $infoPackage = $this->infoPackageRepository->find($infoPackageId);

        if (!$infoPackage instanceof InfoPackage) {
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
            $packageRow = $this->infoPackageRepository->getPackageByOrder($orderId, $shopId);
            if (null === $packageRow || (int) ($packageRow['id_infopackage'] ?? 0) !== $infoPackageId) {
                throw ShipmentGenerationException::infoPackageNotFound($infoPackageId);
            }
        }

        $existingShipmentId = $this->shipmentRepository->shipmentExistsByInfoPackage($infoPackageId);
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
        $companyId = (int) ($companyData['id_carrier_company'] ?? 0);
        if (0 === $companyId) {
            throw ShipmentGenerationException::carrierNotConfigured($referenceCarrierId);
        }

        $typeShipments = $this->resolveTypeShipments($companyId);
        if ([] === $typeShipments) {
            throw ShipmentGenerationException::typeShipmentsMissing($companyId);
        }

        $carrierCode = (string) ($companyData['shortname'] ?? '');

        $payload = [
            'id_order' => $orderId,
            'reference' => (string) $order->reference,
            'info_package' => $this->mapInfoPackage($infoPackage, $referenceCarrierId),
            'info_customer' => $this->mapCustomer($order, $languageId),
            'info_shop' => $this->mapInfoShop($languageId),
            'carriers' => Carrier::getCarriers($languageId, true, false, false, null, false),
            'carrier_name' => $this->resolveCarrierName($referenceCarrierId, $languageId),
            'info_company_carrier' => $companyData,
            'info_type_shipment' => $typeShipments,
            'config_extra_info' => $this->configurationManager->getExtraConfigDefaults(),
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
                $this->generateForInfoPackage($infoPackageId);
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
    private function mapInfoShop(int $languageId): array
    {
        $defaults = $this->configurationManager->getInfoShopDefaults();
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
    private function mapInfoPackage(InfoPackage $infoPackage, int $referenceCarrierId): array
    {
        $hourFrom = $infoPackage->getHourFrom();
        $hourUntil = $infoPackage->getHourUntil();

        return [
            'id_infopackage' => $infoPackage->getId(),
            'id_order' => $infoPackage->getOrderId(),
            'id_reference_carrier' => $referenceCarrierId,
            'id_type_shipment' => $infoPackage->getTypeShipment()->getId(),
            'quantity' => $infoPackage->getQuantity(),
            'weight' => $infoPackage->getWeight(),
            'length' => $infoPackage->getLength(),
            'width' => $infoPackage->getWidth(),
            'height' => $infoPackage->getHeight(),
            'cash_ondelivery' => $infoPackage->getCashOnDelivery(),
            'message' => $infoPackage->getMessage(),
            'hour_from' => $hourFrom ? $hourFrom->format('H:i:s') : null,
            'hour_until' => $hourUntil ? $hourUntil->format('H:i:s') : null,
            'retorno' => $infoPackage->getRetorno(),
            'rcs' => $infoPackage->isRcsEnabled() ? 1 : 0,
            'vsec' => $infoPackage->getVsec(),
            'dorig' => $infoPackage->getDorig(),
        ];
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
            return $this->mapCompany($typeShipment->getCompany());
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveTypeShipments(int $companyId): array
    {
        $company = $this->companyRepository->find($companyId);

        if (!$company instanceof Company) {
            return [];
        }

        $types = $this->typeShipmentRepository->findByCompany($company, true);

        $mapped = [];
        foreach ($types as $typeShipment) {
            $mapped[] = $this->mapTypeShipment($typeShipment, $company);
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCompany(Company $company): array
    {
        return [
            'id_carrier_company' => (int) $company->getId(),
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
    private function mapTypeShipment(TypeShipment $typeShipment, Company $company): array
    {
        return [
            'id_type_shipment' => (int) $typeShipment->getId(),
            'id_carrier_company' => (int) $company->getId(),
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
}
