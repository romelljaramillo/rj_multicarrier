<?php
/**
 * Carrier adapter for Correo Express (CEX).
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

use Exception;
use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use PrestaShop\PrestaShop\Adapter\Country\CountryDataProvider;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use Roanja\Module\RjMulticarrier\Pdf\RjPDF;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Support\Common;
use RuntimeException;

final class CexCarrierAdapter implements CarrierAdapterInterface
{
    private const CODE = 'CEX';

    public function __construct(
        private readonly LegacyConfiguration $configuration,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly CountryDataProvider $countryDataProvider,
        private readonly LegacyContext $legacyContext
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function generateShipment(CarrierContext $context): CarrierGenerationResult
    {
        $payload = $context->getPayload();
        $config = $this->resolveConfiguration();

        $requestData = $this->buildRequestPayload($payload, $config, $context);
        $endpoint = $this->resolveEndpoint($config);

        $responseData = $this->callApi($endpoint, $requestData, $config);

        if ((int) ($responseData['codigoRetorno'] ?? -1) !== 0) {
            $message = (string) ($responseData['mensajeRetorno'] ?? 'Unknown error');
            throw new RuntimeException(sprintf('CEX API error: %s', $message));
        }

        $shipmentNumber = (string) ($responseData['datosResultado'] ?? $context->getShipmentNumber());
        $labels = $this->generateLabels($context, $payload, $config, $responseData);

        $requestPayload = $payload;
        $requestPayload['cex_request'] = $requestData;

        return new CarrierGenerationResult(
            $shipmentNumber,
            $requestPayload,
            $responseData,
            $labels
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|null> $config
     * @return array<string, mixed>
     */
    private function buildRequestPayload(array $payload, array $config, CarrierContext $context): array
    {
        $infoPackage = (array) ($payload['info_package'] ?? []);
        $infoShop = (array) ($payload['info_shop'] ?? []);
        $infoCustomer = (array) ($payload['info_customer'] ?? []);
        $configExtra = (array) ($payload['config_extra_info'] ?? []);

        $typeShipmentId = (int) ($infoPackage['id_type_shipment'] ?? 0);
        $typeShipment = $typeShipmentId > 0 ? $this->typeShipmentRepository->find($typeShipmentId) : null;
        $businessCode = $typeShipment?->getBusinessCode() ?? (string) ($infoPackage['id_bc'] ?? '');

        $data = [
            'solicitante' => $this->transformCodeClient($config['COD_CLIENT'] ?? ''),
            'canalEntrada' => '',
            'numEnvio' => '',
            'ref' => (string) ($payload['id_order'] ?? $context->getOrderId()),
            'refCliente' => (string) ($payload['id_order'] ?? $context->getOrderId()),
            'fecha' => date('dmY'),
            'codRte' => $config['COD_CLIENT'] ?? '',
        ];

        $shipper = $this->buildShipper($infoShop);
        $receiver = $this->buildReceiver($infoCustomer);
        $pieces = $this->buildPieces($infoPackage, $businessCode);
        $additional = [
            'listaInformacionAdicional' => [
                $this->buildAdditionalInfo($payload, $configExtra),
            ],
        ];

        return array_merge($data, $shipper, $receiver, $pieces, $additional);
    }

    /**
     * @param array<string, mixed> $infoPackage
     */
    private function buildPieces(array $infoPackage, string $businessCode): array
    {
        $quantity = max(1, (int) ($infoPackage['quantity'] ?? 1));
        $weight = (float) ($infoPackage['weight'] ?? 0.0);
        $unitWeight = $quantity > 0 ? $weight / $quantity : $weight;

        $listPackages = [];
        for ($index = 1; $index <= $quantity; $index++) {
            $package = new \stdClass();
            $package->alto = '';
            $package->ancho = '';
            $package->codBultoCli = $index;
            $package->codUnico = '';
            $package->descripcion = '';
            $package->kilos = '';
            $package->largo = '';
            $package->observaciones = '';
            $package->orden = $index;
            $package->referencia = '';
            $package->volumen = '';
            $listPackages[] = $package;
        }

        $cashOnDelivery = (float) ($infoPackage['cash_ondelivery'] ?? 0.0);
        $codValue = $cashOnDelivery > 0 ? (string) round($cashOnDelivery, 2) : '';

        return [
            'observac' => (string) ($infoPackage['message'] ?? ''),
            'numBultos' => (string) $quantity,
            'kilos' => (string) round($unitWeight, 2),
            'volumen' => '',
            'alto' => (string) round((float) ($infoPackage['height'] ?? 0), 2),
            'largo' => (string) round((float) ($infoPackage['length'] ?? 0), 2),
            'ancho' => (string) round((float) ($infoPackage['width'] ?? 0), 2),
            'producto' => $businessCode,
            'portes' => 'P',
            'reembolso' => $codValue,
            'entrSabado' => '',
            'seguro' => '',
            'numEnvioVuelta' => '',
            'listaBultos' => $listPackages,
            'codDirecDestino' => !empty($infoPackage['delivery_office']) ? (string) ($infoPackage['cod_office'] ?? '') : '',
            'password' => '',
        ];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function buildShipper(array $info): array
    {
        $countryId = (int) ($info['id_country'] ?? 0);
        $countryIso = '';

        if ($countryId > 0) {
            $countryIso = (string) $this->countryDataProvider->getIsoCodebyId($countryId);
        }

        $countryIso = strtoupper($countryIso);
        $postcode = (string) ($info['postcode'] ?? '');

        $postalNational = '';
        $postalInternational = '';
        if ('ES' === strtoupper($countryIso)) {
            $postalNational = $this->padPostcode($postcode, 5);
        } else {
            $postalInternational = $postcode;
        }

        $firstName = (string) ($info['firstname'] ?? '');
        $lastName = (string) ($info['lastname'] ?? '');
        $contactName = trim($firstName . ' ' . $lastName);

        return [
            'nomRte' => $contactName,
            'nifRte' => (string) ($info['vatnumber'] ?? ''),
            'dirRte' => trim((string) ($info['street'] ?? '') . ' ' . (string) ($info['city'] ?? '')),
            'pobRte' => (string) ($info['city'] ?? ''),
            'codPosNacRte' => $postalNational,
            'paisISORte' => $countryIso,
            'codPosIntRte' => $postalInternational,
            'contacRte' => $contactName,
            'telefRte' => (string) ($info['phone'] ?? ''),
            'emailRte' => (string) ($info['email'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function buildReceiver(array $info): array
    {
        $phone = (string) ($info['phone_mobile'] ?? '');
        if ('' === $phone) {
            $phone = (string) ($info['phone'] ?? '');
        }

        $countryIso = strtoupper((string) ($info['countrycode'] ?? ''));
        $postcode = (string) ($info['postcode'] ?? '');

        $postalNational = '';
        $postalInternational = '';
        if ('ES' === $countryIso) {
            $postalNational = $this->padPostcode($postcode, 5);
        } else {
            $postalInternational = $postcode;
        }

        $firstName = (string) ($info['firstname'] ?? '');
        $lastName = (string) ($info['lastname'] ?? '');
        $contactName = trim($firstName . ' ' . $lastName);

        return [
            'codDest' => '',
            'nomDest' => $contactName,
            'nifDest' => (string) ($info['vat_number'] ?? ''),
            'dirDest' => (string) ($info['address1'] ?? ''),
            'pobDest' => (string) ($info['city'] ?? ''),
            'codPosNacDest' => $postalNational,
            'paisISODest' => $countryIso,
            'codPosIntDest' => $postalInternational,
            'contacDest' => $contactName,
            'telefDest' => $phone,
            'emailDest' => (string) ($info['email'] ?? ''),
            'contacOtrs' => '',
            'telefOtrs' => (string) ($info['phone'] ?? ''),
            'emailOtrs' => '',
        ];
    }

    /**
     * @param array<string, mixed> $infoShipment
     * @return object
     */
    private function buildAdditionalInfo(array $infoShipment, array $configExtra): object
    {
    $context = $this->legacyContext->getContext();
        $languageIso = isset($context->language) ? strtolower((string) $context->language->iso_code) : 'es';
        $language = 'es' === $languageIso ? 'ES' : ('pt' === $languageIso ? 'PT' : strtoupper($languageIso));

        $lista = new \stdClass();
        $lista->tipoEtiqueta = '5';
        $lista->posicionEtiqueta = '0';
        $lista->hideSender = (string) ($configExtra['RJ_LABELSENDER'] ?? ($configExtra['LABELSENDER'] ?? '0'));
        $lista->codificacionUnicaB64 = '1';
        $lista->logoCliente = '';
        $lista->idioma = $language;
        $lista->textoRemiAlternativo = (string) ($configExtra['RJ_LABELSENDER_TEXT'] ?? ($configExtra['LABELSENDER_TEXT'] ?? ''));
        $lista->etiquetaPDF = '';

        $grabarRecogida = (string) ($infoShipment['grabar_recogida'] ?? 'false');
        if ('true' === strtolower($grabarRecogida)) {
            $lista->creaRecogida = 'S';
            $lista->fechaRecogida = date('d-m-Y');
            $lista->horaDesdeRecogida = sprintf('%s:%s', $infoShipment['fromHH_sender'] ?? '00', $infoShipment['fromMM_sender'] ?? '00');
            $lista->horaHastaRecogida = sprintf('%s:%s', $infoShipment['toHH_sender'] ?? '00', $infoShipment['toMM_sender'] ?? '00');
            $lista->referenciaRecogida = (string) ($infoShipment['id'] ?? $infoShipment['id_order'] ?? '');
        } else {
            $lista->creaRecogida = 'N';
        }

        return $lista;
    }

    private function transformCodeClient(string $code): string
    {
        return '' === $code ? '' : 'P' . $code;
    }

    private function padPostcode(string $value, int $length): string
    {
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, mixed>>
     */
    private function generateLabels(CarrierContext $context, array $payload, array $config, array $response): array
    {
        $infoPackage = (array) ($payload['info_package'] ?? []);
        $quantity = max(1, (int) ($infoPackage['quantity'] ?? 1));
        $labelType = $config['LABEL_TYPE'] ?? (string) ($context->getOptions()['label_type'] ?? 'B2X_Generic_A4_Third');
        $display = $config['DISPLAY_PDF'] ?? (string) ($context->getOptions()['display_pdf'] ?? 'S');
        $shortname = $context->getOptions()['shortname'] ?? self::CODE;

        $labels = [];
        $listaBultos = [];
        if (isset($response['listaBultos']) && is_array($response['listaBultos'])) {
            $listaBultos = $response['listaBultos'];
        }

        for ($packageIndex = 1; $packageIndex <= $quantity; $packageIndex++) {
            $pdfGenerator = new RjPDF($shortname, $payload, RjPDF::TEMPLATE_LABEL, $packageIndex);
            $pdfContent = $pdfGenerator->render($display);

            if (!$pdfContent) {
                continue;
            }

            $responseLabel = $listaBultos[$packageIndex - 1] ?? [];
            if ($responseLabel instanceof \stdClass) {
                $responseLabel = (array) $responseLabel;
            }

            $storageKey = (string) ($responseLabel['codUnico'] ?? Common::getUUID());

            $labels[] = [
                'package_id' => $storageKey,
                'storage_key' => $storageKey,
                'tracker_code' => (string) ($responseLabel['codUnico'] ?? sprintf('TC%s-%d', $storageKey, $packageIndex)),
                'label_type' => $labelType,
                'pdf_content' => $pdfContent,
            ];
        }

        return $labels;
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveConfiguration(): array
    {
        $values = [];

        foreach ([
            'URL' => false,
            'WSURL' => false,
            'USER' => false,
            'PASS' => true,
            'COD_CLIENT' => false,
        ] as $suffix => $decrypt) {
            $values[$suffix] = $this->getConfigurationValue($suffix, $decrypt);
        }

        $values['LABEL_TYPE'] = null;
        $values['DISPLAY_PDF'] = null;

        return $values;
    }

    private function resolveEndpoint(array $config): string
    {
        $wsUrl = (string) ($config['WSURL'] ?? '');
        $baseUrl = (string) ($config['URL'] ?? '');

        if ('' === $wsUrl) {
            throw new RuntimeException('CEX endpoint is not configured.');
        }

        if (preg_match('/^https?:/i', $wsUrl)) {
            return $wsUrl;
        }

        if ('' === $baseUrl) {
            throw new RuntimeException('CEX base URL is not configured.');
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($wsUrl, '/');
    }

    /**
     * @param array<string, mixed> $requestData
     * @param array<string, string|null> $config
     * @return array<string, mixed>
     */
    private function callApi(string $endpoint, array $requestData, array $config): array
    {
        try {
            $body = json_encode($requestData, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new RuntimeException('Unable to encode CEX request payload to JSON', 0, $exception);
        }

        $resource = curl_init();

        curl_setopt_array($resource, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'RJ Multicarrier CEX Adapter/1.0',
            CURLOPT_URL => $endpoint,
            CURLOPT_USERPWD => sprintf('%s:%s', $config['USER'] ?? '', $config['PASS'] ?? ''),
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($body),
            ],
        ]);

        $responseRaw = curl_exec($resource);
        $curlError = curl_error($resource);
        $statusCode = (int) curl_getinfo($resource, CURLINFO_HTTP_CODE);
        curl_close($resource);

        if (false === $responseRaw) {
            throw new RuntimeException(sprintf('CEX request failed: %s', $curlError ?: 'unknown error'));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('CEX request returned HTTP %d: %s', $statusCode, $responseRaw));
        }

        try {
            $decoded = json_decode($responseRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new RuntimeException('Unable to decode CEX response payload', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected CEX response structure.');
        }

        return $decoded;
    }

    private function getConfigurationValue(string $suffix, bool $decrypt = false): ?string
    {
        $keys = [sprintf('%s_%s', self::CODE, $suffix), sprintf('RJ_%s_%s', self::CODE, $suffix)];

        foreach ($keys as $key) {
            $value = $this->configuration->get($key);

            if (null === $value || '' === (string) $value) {
                continue;
            }

            $value = (string) $value;

            if ('' !== $value) {
                return $decrypt ? $this->maybeDecrypt($value) : $value;
            }
        }

        return null;
    }

    private function maybeDecrypt(string $value): string
    {
        try {
            $decrypted = Common::encrypt('decrypt', $value);
            return '' === $decrypted ? $value : $decrypted;
        } catch (Exception) {
            return $value;
        }
    }
}
