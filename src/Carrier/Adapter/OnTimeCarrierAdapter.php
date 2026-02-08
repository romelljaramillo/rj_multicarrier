<?php
/**
 * Carrier adapter for ON Time (Ontime) API integration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

use Exception;
use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use PrestaShop\PrestaShop\Adapter\Country\CountryDataProvider;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use Roanja\Module\RjMulticarrier\Domain\Log\Command\CreateLogEntryCommand;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Support\Common;
use RuntimeException;

final class OnTimeCarrierAdapter implements CarrierAdapterInterface
{
    private const CODE = 'ONT';
    private const VERSION = 3;
    private const DEFAULT_LABEL_FORMAT = 'PDF';

    public function __construct(
        private readonly LegacyConfiguration $configuration,
        private readonly CountryDataProvider $countryDataProvider,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly CommandBusInterface $commandBus
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getDescriptor(): CarrierAdapterDescriptor
    {
        return new CarrierAdapterDescriptor(
            self::CODE,
            'ON Time',
            'Integra la API de ON Time para documentar envios y etiquetas.',
            null,
            true,
            null,
            null,
        );
    }

    public function generateShipment(CarrierContext $context): CarrierGenerationResult
    {
        $payload = $context->getPayload();
        $options = $context->getOptions();
        $carrierSettings = $payload['carrier_settings'] ?? [];

        $config = $this->resolveConfiguration($carrierSettings);

        $shipmentNumber = $context->getShipmentNumber() ?: Common::getUUID();
        $payload['num_shipment'] = $shipmentNumber;

        $admissionCode = $this->buildAdmissionCode($payload, $context, (string) $shipmentNumber);
        $requestPayload = $this->buildShipmentPayload($context, $payload, $config, $admissionCode);

        $responsePayload = $this->documentShipment($config, $requestPayload);
        $documentResult = $this->extractDocumentResult($responsePayload, $admissionCode);

        $ontimeShipmentNumber = (string) ($documentResult['numero_envio'] ?? $shipmentNumber);
        $labels = $this->buildLabels($documentResult, $options, $ontimeShipmentNumber, $config['label_format']);

        $requestSnapshot = $payload;
        $requestSnapshot['ontime_request'] = $requestPayload;

        return new CarrierGenerationResult(
            $ontimeShipmentNumber,
            $requestSnapshot,
            $documentResult,
            $labels
        );
    }

    public static function getDefaultConfiguration(): array
    {
        return [
            [
                'name' => 'ONT_ENV',
                'label' => 'Modo produccion',
                'value' => '0',
                'required' => true,
                'field_type' => 'boolean',
            ],
            [
                'name' => 'ONT_URL',
                'label' => 'Base URL produccion',
                'description' => 'Ejemplo: https://ontimegts.alertran.net',
                'required' => true,
            ],
            [
                'name' => 'ONT_URL_DEV',
                'label' => 'Base URL preproduccion',
                'description' => 'Ejemplo: https://gtsontimepre.alertran.net',
                'required' => true,
            ],
            [
                'name' => 'ONT_ENDPOINT_DOCUMENTAR',
                'label' => 'Endpoint documentar envios',
                'description' => 'Ejemplo: /gts/seam/resource/restv1/auth/documentarEnvio/json',
                'required' => true,
            ],
            [
                'name' => 'ONT_USER',
                'label' => 'Usuario',
                'required' => true,
            ],
            [
                'name' => 'ONT_PASSWORD',
                'label' => 'Password',
                'required' => true,
                'field_type' => 'password',
            ],
            [
                'name' => 'ONT_CLIENTE',
                'label' => 'Codigo cliente remitente',
                'required' => true,
            ],
            [
                'name' => 'ONT_CENTRO',
                'label' => 'Codigo centro remitente',
                'required' => true,
            ],
            [
                'name' => 'ONT_DEPARTAMENTO',
                'label' => 'Departamento remitente',
                'required' => false,
            ],
            [
                'name' => 'ONT_TIPO_PORTES',
                'label' => 'Tipo portes',
                'description' => 'P=Pagados, D=Debidos',
                'value' => 'P',
                'required' => true,
            ],
            [
                'name' => 'ONT_LABEL_FORMAT',
                'label' => 'Formato etiqueta',
                'description' => 'PDF o ZPL',
                'value' => self::DEFAULT_LABEL_FORMAT,
                'required' => true,
            ],
            [
                'name' => 'TRACKING_URL',
                'label' => 'URL seguimiento',
                'description' => 'Ejemplo: http://ejemplo.com/track.php?num=@',
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfiguration(array $carrierSettings = []): array
    {
        $envRaw = $this->fetchConfigurationValue('ENV', $carrierSettings);
        $isProduction = (int) $envRaw === 1;
        $envSuffix = $isProduction ? '' : '_DEV';

        $map = [
            'base_url' => 'URL' . $envSuffix,
            'endpoint_documentar' => 'ENDPOINT_DOCUMENTAR',
            'user' => 'USER',
            'password' => 'PASSWORD',
            'cliente' => 'CLIENTE',
            'centro' => 'CENTRO',
            'departamento' => 'DEPARTAMENTO',
            'tipo_portes' => 'TIPO_PORTES',
            'label_format' => 'LABEL_FORMAT',
        ];

        $config = ['is_production' => $isProduction];
        foreach ($map as $label => $suffix) {
            $config[$label] = $this->fetchConfigurationValue($suffix, $carrierSettings);
        }

        if ('' === $config['base_url']) {
            throw new RuntimeException('ON Time base URL is not configured.');
        }

        if ('' === $config['endpoint_documentar']) {
            throw new RuntimeException('ON Time document endpoint is not configured.');
        }

        if ('' === $config['user'] || '' === $config['password']) {
            throw new RuntimeException('ON Time credentials are not configured.');
        }

        if ('' === $config['cliente'] || '' === $config['centro']) {
            throw new RuntimeException('ON Time sender codes are not configured.');
        }

        $config['tipo_portes'] = '' !== $config['tipo_portes'] ? $config['tipo_portes'] : 'P';
        $config['label_format'] = $this->normalizeLabelFormat($config['label_format']);

        return $config;
    }

    private function fetchConfigurationValue(string $suffix, array $carrierSettings = []): string
    {
        $keys = [
            sprintf('%s_%s', self::CODE, $suffix),
            sprintf('RJ_%s_%s', self::CODE, $suffix),
        ];

        foreach ($keys as $key) {
            if (isset($carrierSettings[$key]) && '' !== trim((string) $carrierSettings[$key])) {
                return (string) $carrierSettings[$key];
            }

            $value = $this->configuration->get($key);
            if (null !== $value && '' !== trim((string) $value)) {
                return (string) $value;
            }
        }

        return '';
    }

    private function normalizeLabelFormat(?string $format): string
    {
        $normalized = strtoupper(trim((string) $format));
        if ('ZPL' === $normalized) {
            return 'ZPL';
        }

        return self::DEFAULT_LABEL_FORMAT;
    }

    private function buildAdmissionCode(array $payload, CarrierContext $context, string $shipmentNumber): string
    {
        $candidate = (string) ($payload['codigo_admision'] ?? '');
        if ('' === $candidate) {
            $orderId = (string) $context->getOrderId();
            $candidate = '' !== $orderId ? $orderId : $shipmentNumber;
        }

        $candidate = preg_replace('/\s+/', '', $candidate) ?? '';
        if ('' === $candidate) {
            $candidate = Common::getUUID();
        }

        return substr($candidate, 0, 30);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildShipmentPayload(CarrierContext $context, array $payload, array $config, string $admissionCode): array
    {
        $infoCustomer = (array) ($payload['info_customer'] ?? []);
        $infoPackage = (array) ($payload['info_package'] ?? []);
        $configuration = (array) ($payload['configuration_shop'] ?? []);
        $infoTypeShipment = (array) ($payload['info_type_shipment'] ?? []);

        $typeShipmentId = (int) ($infoPackage['id_type_shipment'] ?? 0);
        $typeShipment = $typeShipmentId > 0 ? $this->typeShipmentRepository->find($typeShipmentId) : null;
        $businessCode = $typeShipment?->getBusinessCode() ?? (string) ($infoTypeShipment['id_bc'] ?? $infoPackage['id_bc'] ?? '');
        if ('' === $businessCode) {
            throw new RuntimeException('ON Time service product code is required.');
        }

        $numPackages = max(1, (int) ($infoPackage['quantity'] ?? 1));
        $weight = (float) ($infoPackage['weight'] ?? 0.0);

        $message = (string) ($infoPackage['message'] ?? '');
        $cashOnDelivery = (float) ($infoPackage['cash_ondelivery'] ?? 0.0);

        $sender = $this->buildSender($configuration, $config);
        $recipient = $this->buildRecipient($infoCustomer);

        $orderReference = $context->getOrderReference();
        $clientReference = '' !== (string) $orderReference ? (string) $orderReference : (string) $context->getOrderId();

        $retorno = $this->toFlag($infoPackage['retorno'] ?? null);
        $rcs = $this->toFlag($infoPackage['rcs'] ?? null);

        $volume = $this->calculateVolume($infoPackage);

        return [
            'CODIGO_ADMISION' => $admissionCode,
            'NUMERO_BULTOS' => $numPackages,
            'CLIENTE_REMITENTE' => $config['cliente'],
            'CENTRO_REMITENTE' => $config['centro'],
            'DEPARTAMENTO_REMITENTE' => $config['departamento'] ?: '',
            'NIF_REMITENTE' => $sender['nif'],
            'NOMBRE_REMITENTE' => $sender['name'],
            'DIRECCION_REMITENTE' => $sender['address'],
            'PAIS_REMITENTE' => $sender['country'],
            'CODIGO_POSTAL_REMITENTE' => $sender['postcode'],
            'POBLACION_REMITENTE' => $sender['city'],
            'TELEFONO_CONTACTO_REMITENTE' => $sender['phone'],
            'PERSONA_CONTACTO_REMITENTE' => $sender['contact'],
            'EMAIL_REMITENTE' => $sender['email'],
            'NIF_DESTINATARIO' => $recipient['nif'],
            'NOMBRE_DESTINATARIO' => $recipient['name'],
            'DIRECCION_DESTINATARIO' => $recipient['address'],
            'PAIS_DESTINATARIO' => $recipient['country'],
            'CODIGO_POSTAL_DESTINATARIO' => $recipient['postcode'],
            'POBLACION_DESTINATARIO' => $recipient['city'],
            'TELEFONO_CONTACTO_DESTINATARIO' => $recipient['phone'],
            'PERSONA_CONTACTO_DESTINATARIO' => $recipient['contact'],
            'EMAIL_DESTINATARIO' => $recipient['email'],
            'CODIGO_PRODUCTO_SERVICIO' => $businessCode,
            'KILOS' => $this->formatNumber($weight),
            'VOLUMEN' => $volume > 0 ? $this->formatNumber($volume) : '',
            'CLIENTE_REFERENCIA' => $clientReference,
            'IMPORTE_REEMBOLSO' => $cashOnDelivery > 0 ? $this->formatNumber($cashOnDelivery, 2) : '',
            'TIPO_PORTES' => $config['tipo_portes'],
            'OBSERVACIONES1' => '' !== $message ? $message : '',
            'OBSERVACIONES2' => '',
            'RETORNO' => $retorno,
            'WEEX_DEVOLUCION_CONFORME' => $rcs,
            'ENVIO_CON_RECOGIDA' => 'N',
            'IMPRIMIR_ETIQUETA' => 'S',
            'ENVIO_DEFINITIVO' => 'N',
            'TIPO_FORMATO' => $config['label_format'],
            'ENTREGA_APLAZADA' => 'N',
            'FECHA_ENTREGA_APLAZADA' => '',
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $config
     * @return array{name:string, address:string, city:string, postcode:string, country:string, phone:string, contact:string, email:string, nif:string}
     */
    private function buildSender(array $configuration, array $config): array
    {
        $countryId = (int) ($configuration['id_country'] ?? 0);
        $countryIso = $countryId > 0 ? (string) $this->countryDataProvider->getIsoCodebyId($countryId) : '';

        $company = trim((string) ($configuration['company'] ?? ''));
        $firstName = trim((string) ($configuration['firstname'] ?? ''));
        $lastName = trim((string) ($configuration['lastname'] ?? ''));
        $contactName = trim($firstName . ' ' . $lastName);

        $name = '' !== $company ? $company : ($contactName ?: $config['cliente']);
        $address = trim((string) ($configuration['street'] ?? '') . ' ' . (string) ($configuration['number'] ?? ''));

        return [
            'name' => $name,
            'address' => trim($address),
            'city' => (string) ($configuration['city'] ?? ''),
            'postcode' => (string) ($configuration['postcode'] ?? ''),
            'country' => '' !== $countryIso ? strtoupper($countryIso) : 'ES',
            'phone' => (string) ($configuration['phone'] ?? ''),
            'contact' => '' !== $contactName ? $contactName : $name,
            'email' => (string) ($configuration['email'] ?? ''),
            'nif' => (string) ($configuration['vatnumber'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $info
     * @return array{name:string, address:string, city:string, postcode:string, country:string, phone:string, contact:string, email:string, nif:string}
     */
    private function buildRecipient(array $info): array
    {
        $firstName = trim((string) ($info['firstname'] ?? ''));
        $lastName = trim((string) ($info['lastname'] ?? ''));
        $contact = trim($firstName . ' ' . $lastName);

        $company = trim((string) ($info['company'] ?? ''));
        $name = '' !== $company ? $company : $contact;

        $address = trim((string) ($info['address1'] ?? '') . ' ' . (string) ($info['address2'] ?? ''));

        $phone = (string) ($info['phone_mobile'] ?? '');
        if ('' === $phone) {
            $phone = (string) ($info['phone'] ?? '');
        }

        return [
            'name' => $name,
            'address' => trim($address),
            'city' => (string) ($info['city'] ?? ''),
            'postcode' => (string) ($info['postcode'] ?? ''),
            'country' => strtoupper((string) ($info['countrycode'] ?? 'ES')),
            'phone' => $phone,
            'contact' => '' !== $contact ? $contact : $name,
            'email' => (string) ($info['email'] ?? ''),
            'nif' => (string) ($info['vat_number'] ?? $info['dni'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $documentPayload
     * @return array<string, mixed>
     */
    private function documentShipment(array $config, array $documentPayload): array
    {
        $endpoint = $this->buildUrl($config['base_url'], $config['endpoint_documentar']);

        $payload = [
            'DOCUMENTAR_ENVIOS' => [
                'VERSION' => self::VERSION,
                'DOCUMENTAR_ENVIO' => [$documentPayload],
            ],
        ];

        return $this->sendJsonRequest('POST', $endpoint, $payload, $config['user'], $config['password']);
    }

    /**
     * @param array<string, mixed> $responsePayload
     * @return array<string, mixed>
     */
    private function extractDocumentResult(array $responsePayload, string $admissionCode): array
    {
        $responses = $this->normalizeDocumentResponses($responsePayload);
        if ([] === $responses) {
            throw new RuntimeException('ON Time response did not include document results.');
        }

        foreach ($responses as $response) {
            if (!is_array($response)) {
                continue;
            }

            $code = (string) ($response['codigo_admision'] ?? '');
            if ('' !== $code && $code === $admissionCode) {
                return $this->validateDocumentResult($response);
            }
        }

        if (1 === count($responses) && is_array($responses[0])) {
            return $this->validateDocumentResult($responses[0]);
        }

        throw new RuntimeException('ON Time response does not match the admission code.');
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function validateDocumentResult(array $response): array
    {
        $result = strtoupper((string) ($response['resultado'] ?? ''));
        if ('OK' !== $result) {
            $message = (string) ($response['mensaje'] ?? 'Unknown error');
            $code = (string) ($response['codigo_error'] ?? '');
            $details = '' !== $code ? sprintf(' (code %s)', $code) : '';
            throw new RuntimeException(sprintf('ON Time API error: %s%s', $message, $details));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $responsePayload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDocumentResponses(array $responsePayload): array
    {
        $items = $this->isList($responsePayload) ? $responsePayload : [$responsePayload];
        $responses = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item['respuestaDocuemtarEnvio']) && is_array($item['respuestaDocuemtarEnvio'])) {
                $responses[] = $item['respuestaDocuemtarEnvio'];
                continue;
            }

            if (isset($item['respuestaDocumentarEnvio']) && is_array($item['respuestaDocumentarEnvio'])) {
                $responses[] = $item['respuestaDocumentarEnvio'];
                continue;
            }

            if (isset($item['respuestaDocumentarEnvios']) && is_array($item['respuestaDocumentarEnvios'])) {
                $responses[] = $item['respuestaDocumentarEnvios'];
                continue;
            }

            if (isset($item['resultado'])) {
                $responses[] = $item;
            }
        }

        return $responses;
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    private function buildLabels(array $response, array $options, string $shipmentNumber, string $labelFormat): array
    {
        $labelBase64 = (string) ($response['etiqueta'] ?? '');
        if ('' === $labelBase64) {
            throw new RuntimeException('ON Time response did not include label data.');
        }

        $decoded = base64_decode($labelBase64, true);
        if (false === $decoded) {
            throw new RuntimeException('Unable to decode ON Time label payload.');
        }

        $labelType = isset($options['label_type']) ? (string) $options['label_type'] : 'ONT_' . $labelFormat;
        $packageId = $shipmentNumber !== '' ? $shipmentNumber : Common::getUUID();

        return [[
            'package_id' => $packageId,
            'storage_key' => $packageId,
            'tracker_code' => (string) ($response['numero_envio'] ?? $shipmentNumber),
            'label_type' => $labelType,
            'pdf_content' => $decoded,
        ]];
    }

    private function buildUrl(string $baseUrl, string $endpoint): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sendJsonRequest(string $method, string $url, array $payload, string $user, string $password): array
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new RuntimeException('Unable to encode ON Time request payload to JSON.', 0, $exception);
        }

        $result = $this->executeRequest($method, $url, $body, [
            'Content-Type: application/json',
            'Accept: application/json',
        ], $user, $password);

        if (!str_contains(strtolower($result['content_type']), 'application/json')) {
            throw new RuntimeException(sprintf('Unexpected ON Time response content type %s for %s.', $result['content_type'], $url));
        }

        try {
            $decoded = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new RuntimeException('Unable to decode ON Time response payload.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected ON Time response structure.');
        }

        return $decoded;
    }

    /**
     * @param string[] $headers
     * @return array{status:int, content_type:string, body:string}
     */
    private function executeRequest(string $method, string $url, ?string $body, array $headers, string $user, string $password): array
    {
        $resource = curl_init();
        if (false === $resource) {
            throw new RuntimeException('Unable to initialize cURL resource for ON Time request.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => sprintf('%s:%s', $user, $password),
        ];

        if (null !== $body) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($resource, $options);

        $response = curl_exec($resource);
        $error = curl_error($resource);
        $status = (int) curl_getinfo($resource, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($resource, CURLINFO_CONTENT_TYPE);
        curl_close($resource);

        if (false === $response) {
            $this->commandBus->handle(new CreateLogEntryCommand(
                $url,
                0,
                (string) $body,
                sprintf('ON Time request failed: %s', $error ?: 'unknown error')
            ));

            throw new RuntimeException(sprintf('ON Time request failed: %s', $error ?: 'unknown error'));
        }

        if ($status < 200 || $status >= 300) {
            $this->commandBus->handle(new CreateLogEntryCommand(
                $url,
                0,
                (string) $body,
                sprintf('HTTP %d: %s', $status, $response)
            ));

            throw new RuntimeException(sprintf('ON Time request returned HTTP %d: %s', $status, $response));
        }

        return [
            'status' => $status,
            'content_type' => $contentType,
            'body' => $response,
        ];
    }

    private function toFlag(mixed $value): string
    {
        if (null === $value) {
            return 'N';
        }

        if (is_bool($value)) {
            return $value ? 'S' : 'N';
        }

        $normalized = strtolower(trim((string) $value));
        if ('' === $normalized) {
            return 'N';
        }

        if (in_array($normalized, ['1', 's', 'si', 'yes', 'true'], true)) {
            return 'S';
        }

        return 'N';
    }

    /**
     * @param array<string, mixed> $infoPackage
     */
    private function calculateVolume(array $infoPackage): float
    {
        $length = (float) ($infoPackage['length'] ?? 0.0);
        $width = (float) ($infoPackage['width'] ?? 0.0);
        $height = (float) ($infoPackage['height'] ?? 0.0);

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return 0.0;
        }

        return ($length * $width * $height) / 1000000;
    }

    private function formatNumber(float $value, int $decimals = 3): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isList(array $value): bool
    {
        if ([] === $value) {
            return false;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
