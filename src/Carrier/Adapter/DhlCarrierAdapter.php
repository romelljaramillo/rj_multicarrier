<?php
/**
 * Carrier adapter for DHL API integration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

use Exception;
use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use PrestaShop\PrestaShop\Adapter\Country\CountryDataProvider;
use Roanja\Module\RjMulticarrier\Support\Common;
use RuntimeException;

final class DhlCarrierAdapter implements CarrierAdapterInterface
{
    private const CODE = 'DHL';

    private const LOGIN_HEADERS = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    public function __construct(
        private readonly LegacyConfiguration $configuration,
        private readonly CountryDataProvider $countryDataProvider
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function generateShipment(CarrierContext $context): CarrierGenerationResult
    {
        $payload = $context->getPayload();
        $options = $context->getOptions();
        $config = $this->resolveConfiguration();

        $shipmentNumber = $context->getShipmentNumber() ?: Common::getUUID();
        $payload['num_shipment'] = $shipmentNumber;

        $requestPayload = $this->buildShipmentPayload($context, $payload, $config);
        $accessToken = $this->authenticate($config);

        $shipmentResponse = $this->createShipment($config, $accessToken, $requestPayload);
        $labels = $this->collectLabels($config, $accessToken, $shipmentResponse, $options);

        $requestSnapshot = $payload;
        $requestSnapshot['dhl_request'] = $requestPayload;

        return new CarrierGenerationResult(
            $shipmentNumber,
            $requestSnapshot,
            $shipmentResponse,
            $labels
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfiguration(): array
    {
        $envRaw = $this->fetchConfigurationValue('ENV');
        $isProduction = (int) $envRaw === 1;
        $envSuffix = $isProduction ? '' : '_DEV';

        $map = [
            'account_id' => 'ACCOUNID',
            'user_id' => 'USERID' . $envSuffix,
            'api_key' => 'KEY' . $envSuffix,
            'base_url' => 'URL' . $envSuffix,
            'endpoint_login' => 'ENDPOINT_LOGIN',
            'endpoint_refresh' => 'ENDPOINT_REFRESH_TOKEN',
            'endpoint_shipment' => 'ENDPOINT_SHIPMENT',
            'endpoint_label' => 'ENDPOINT_LABEL',
        ];

        $config = ['is_production' => $isProduction];

        foreach ($map as $label => $suffix) {
            $config[$label] = $this->fetchConfigurationValue($suffix);
        }

        if ('' === $config['user_id'] || '' === $config['api_key']) {
            throw new RuntimeException('DHL credentials are not configured.');
        }

        if ('' === $config['base_url']) {
            throw new RuntimeException('DHL base URL is not configured.');
        }

        return $config;
    }

    private function fetchConfigurationValue(string $suffix): string
    {
        $keys = [
            sprintf('%s_%s', self::CODE, $suffix),
            sprintf('RJ_%s_%s', self::CODE, $suffix),
        ];

        foreach ($keys as $key) {
            $value = $this->configuration->get($key);
            if (null !== $value && '' !== trim((string) $value)) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function buildShipmentPayload(CarrierContext $context, array $payload, array $config): array
    {
        $orderId = $context->getOrderId();
        $shipmentNumber = (string) ($payload['num_shipment'] ?? $context->getShipmentNumber());
        if ('' === $shipmentNumber) {
            $shipmentNumber = Common::getUUID();
        }

        $infoCustomer = (array) ($payload['info_customer'] ?? []);
        $Configuration = (array) ($payload['configuration_shop'] ?? []);
        $infoPackage = (array) ($payload['info_package'] ?? []);
        $infoCustomer['referenceClient'] = (string) ($infoPackage['message'] ?? '');

        $receiver = $this->buildReceiver($infoCustomer);
        $shipper = $this->buildShipper($Configuration);
        $pieces = $this->buildPieces($infoPackage);

        $options = [[
            'key' => 'REFERENCE',
            'input' => (string) $orderId,
        ]];

        $cashOnDelivery = (float) ($infoPackage['cash_ondelivery'] ?? 0);
        if ($cashOnDelivery > 0) {
            $options[] = [
                'key' => 'COD_CASH',
                'input' => $cashOnDelivery,
            ];
        }

        return [
            'shipmentId' => $shipmentNumber,
            'orderReference' => (string) $orderId,
            'receiver' => $receiver,
            'shipper' => $shipper,
            'accountId' => (string) $config['account_id'],
            'options' => $options,
            'returnLabel' => false,
            'pieces' => $pieces,
        ];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function buildReceiver(array $info): array
    {
        $phone = '';
        if (!empty($info['phone_mobile'])) {
            $phone = (string) $info['phone_mobile'];
        } elseif (!empty($info['phone'])) {
            $phone = (string) $info['phone'];
        }

        return [
            'name' => [
                'firstName' => (string) ($info['firstname'] ?? ''),
                'lastName' => (string) ($info['lastname'] ?? ''),
                'companyName' => (string) ($info['company'] ?? ''),
                'additionalName' => (string) ($info['firstname'] ?? ''),
            ],
            'address' => [
                'countryCode' => (string) ($info['countrycode'] ?? ''),
                'postalCode' => (string) ($info['postcode'] ?? ''),
                'city' => (string) ($info['city'] ?? ''),
                'street' => (string) ($info['address1'] ?? ''),
                'additionalAddressLine' => (string) ($info['address2'] ?? ''),
                'number' => '',
                'isBusiness' => !empty($info['company']),
                'addition' => (string) ($info['other'] ?? ''),
            ],
            'email' => (string) ($info['email'] ?? ''),
            'phoneNumber' => $phone,
            'vatNumber' => (string) ($info['vat_number'] ?? ''),
            'eoriNumber' => (string) ($info['dni'] ?? ''),
            'reference' => (string) ($info['referenceClient'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function buildShipper(array $info): array
    {
        $countryId = (int) ($info['id_country'] ?? 0);
        $countryCode = $countryId > 0 ? (string) $this->countryDataProvider->getIsoCodebyId($countryId) : '';

        return [
            'name' => [
                'firstName' => (string) ($info['firstname'] ?? ''),
                'lastName' => (string) ($info['lastname'] ?? ''),
                'companyName' => (string) ($info['company'] ?? ''),
                'additionalName' => (string) ($info['additionalname'] ?? ''),
            ],
            'address' => [
                'countryCode' => $countryCode,
                'postalCode' => (string) ($info['postcode'] ?? ''),
                'city' => (string) ($info['state'] ?? ''),
                'street' => trim(((string) ($info['street'] ?? '')) . ' ' . ((string) ($info['city'] ?? ''))),
                'additionalAddressLine' => (string) ($info['additionaladdress'] ?? ''),
                'number' => (string) ($info['number'] ?? ''),
                'isBusiness' => !empty($info['company']),
                'addition' => '',
            ],
            'email' => (string) ($info['email'] ?? ''),
            'phoneNumber' => (string) ($info['phone'] ?? ''),
            'vatNumber' => (string) ($info['vatnumber'] ?? ''),
            'eoriNumber' => '',
        ];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<int, array<string, mixed>>
     */
    private function buildPieces(array $info): array
    {
        $quantity = max(1, (int) ($info['quantity'] ?? 1));
        $weight = (float) ($info['weight'] ?? 0);
        $unitWeight = $quantity > 0 ? $weight / $quantity : $weight;

        return [[
            'parcelType' => 'SMALL',
            'quantity' => $quantity,
            'weight' => round($unitWeight, 3),
            'dimensions' => [
                'length' => (float) ($info['length'] ?? 0),
                'width' => (float) ($info['width'] ?? 0),
                'height' => (float) ($info['height'] ?? 0),
            ],
        ]];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function authenticate(array $config): string
    {
        $endpoint = $this->buildUrl($config['base_url'], $config['endpoint_login']);
        $body = [
            'userId' => $config['user_id'],
            'key' => $config['api_key'],
        ];

        $response = $this->sendJsonRequest('POST', $endpoint, $body, self::LOGIN_HEADERS);

        $token = isset($response['accessToken']) ? (string) $response['accessToken'] : '';
        if ('' === $token) {
            throw new RuntimeException('DHL authentication did not return an access token.');
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function createShipment(array $config, string $token, array $payload): array
    {
        $endpoint = $this->buildUrl($config['base_url'], $config['endpoint_shipment']);

        return $this->sendJsonRequest('POST', $endpoint, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $this->buildBearer($token),
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $response
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    private function collectLabels(array $config, string $token, array $response, array $options): array
    {
        $pieces = isset($response['pieces']) && is_array($response['pieces']) ? $response['pieces'] : [];
        if ([] === $pieces) {
            throw new RuntimeException('DHL shipment response does not include pieces/labels.');
        }

        $labelType = isset($options['label_type']) ? (string) $options['label_type'] : 'DHL_PDF';
        $labels = [];

        foreach ($pieces as $index => $piece) {
            if (!is_array($piece)) {
                $piece = (array) $piece;
            }

            $labelId = (string) ($piece['labelId'] ?? '');
            if ('' === $labelId) {
                throw new RuntimeException('DHL shipment piece is missing labelId.');
            }

            $labelData = $this->downloadLabel($config, $token, $labelId);

            $labels[] = [
                'package_id' => $labelId,
                'storage_key' => $labelId,
                'tracker_code' => (string) ($piece['trackerCode'] ?? sprintf('TC%s-%d', $labelId, $index + 1)),
                'label_type' => $labelType,
                'pdf_content' => $labelData,
            ];
        }

        return $labels;
    }

    private function downloadLabel(array $config, string $token, string $labelId): string
    {
        $endpoint = $this->buildUrl($config['base_url'], rtrim((string) $config['endpoint_label'], '/') . '/' . $labelId);
        $response = $this->sendJsonRequest('GET', $endpoint, null, [
            'Accept: application/json',
            'Authorization: ' . $this->buildBearer($token),
        ]);

        $pdfBase64 = isset($response['pdf']) ? (string) $response['pdf'] : '';
        if ('' === $pdfBase64) {
            throw new RuntimeException(sprintf('DHL label %s response does not contain PDF data.', $labelId));
        }

        $decoded = base64_decode($pdfBase64, true);
        if (false === $decoded) {
            throw new RuntimeException(sprintf('Unable to decode DHL label %s PDF content.', $labelId));
        }

        return $decoded;
    }

    private function buildUrl(string $baseUrl, string $endpoint): string
    {
        if ('' === $endpoint) {
            throw new RuntimeException('DHL endpoint is not configured.');
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    private function buildBearer(string $token): string
    {
        return str_starts_with($token, 'Bearer ') ? $token : 'Bearer ' . $token;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param string[] $headers
     * @return array<string, mixed>
     */
    private function sendJsonRequest(string $method, string $url, ?array $payload, array $headers): array
    {
        $body = null;
        if (null !== $payload) {
            try {
                $body = json_encode($payload, JSON_THROW_ON_ERROR);
            } catch (Exception $exception) {
                throw new RuntimeException('Unable to encode DHL request payload to JSON.', 0, $exception);
            }
        }

        $result = $this->executeRequest($method, $url, $body, $headers);

        if (!str_contains(strtolower($result['content_type']), 'application/json')) {
            throw new RuntimeException(sprintf('Unexpected DHL response content type %s for %s.', $result['content_type'], $url));
        }

        try {
            $decoded = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new RuntimeException('Unable to decode DHL response payload.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected DHL response structure.');
        }

        return $decoded;
    }

    /**
     * @param string[] $headers
     * @return array{status:int, content_type:string, body:string}
     */
    private function executeRequest(string $method, string $url, ?string $body, array $headers): array
    {
        $resource = curl_init();
        if (false === $resource) {
            throw new RuntimeException('Unable to initialize cURL resource for DHL request.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
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
            throw new RuntimeException(sprintf('DHL request failed: %s', $error ?: 'unknown error'));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('DHL request returned HTTP %d: %s', $status, $response));
        }

        return [
            'status' => $status,
            'content_type' => $contentType,
            'body' => $response,
        ];
    }
}
