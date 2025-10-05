<?php
/**
 * Carrier adapter for GOI logistics service.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

use Exception;
use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use RuntimeException;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Support\Common;

final class GoiCarrierAdapter implements CarrierAdapterInterface
{
    private const CODE = 'GOI';
    private const MAX_LABEL_ATTEMPTS = 10;
    private const LABEL_RETRY_DELAY_MICROSECONDS = 200000;

    public function __construct(
        private readonly LegacyConfiguration $configuration,
        private readonly TypeShipmentRepository $typeShipmentRepository
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

        $requestBody = $this->buildShipmentPayload($context, $payload, $config);
        $loginResponse = $this->authenticate($config);
        $accessToken = (string) ($loginResponse['access_token'] ?? '');

        if ('' === $accessToken) {
            throw new RuntimeException('GOI authentication returned an empty access token.');
        }

        $shipmentResponse = $this->createShipment($config, $accessToken, $requestBody);
        $labels = $this->downloadLabels($config, $accessToken, $context, $options);

        $requestSnapshot = $payload;
        $requestSnapshot['goi_request'] = $requestBody;

        return new CarrierGenerationResult(
            $context->getShipmentNumber(),
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
        $isProduction = (int) $this->configuration->get('RJ_' . self::CODE . '_ENV') === 1;
        $suffix = $isProduction ? '' : '_DEV';

        $map = [
            'user_id' => 'USERID' . $suffix,
            'store_id' => 'STOREID' . $suffix,
            'key' => 'KEY' . $suffix,
            'base_url' => 'URL' . $suffix,
            'endpoint_login' => 'ENDPOINT_LOGIN',
            'endpoint_shipment' => 'ENDPOINT_SHIPMENT',
            'endpoint_label' => 'ENDPOINT_LABEL',
        ];

        $config = [];
        foreach ($map as $label => $configKey) {
            $value = $this->configuration->get('RJ_' . self::CODE . '_' . $configKey);
            if (null === $value || '' === trim((string) $value)) {
                throw new RuntimeException(sprintf('Missing GOI configuration value for key %s.', $configKey));
            }

            $config[$label] = (string) $value;
        }

        return $config;
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
        if ($orderId <= 0) {
            throw new RuntimeException('GOI shipment requires a valid order identifier.');
        }

        $infoCustomer = (array) ($payload['info_customer'] ?? []);
        $infoPackage = (array) ($payload['info_package'] ?? []);
        $infoCustomer['notes'] = (string) ($infoPackage['message'] ?? '');

        $receiver = $this->buildReceiver($infoCustomer);
        $pieces = $this->buildPieces($infoPackage);
        $products = $this->buildProducts($orderId);
        $services = $this->resolveServices((int) ($infoPackage['id_type_shipment'] ?? 0));

        $metadata = ['id_order' => (string) $orderId];

        return array_merge(
            [
                'order_id' => (string) $orderId,
                'store_id' => $config['store_id'],
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'services' => $services,
            ],
            $receiver,
            $pieces,
            $products
        );
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function authenticate(array $config): array
    {
        $endpoint = $this->buildUrl($config['base_url'], $config['endpoint_login']);

        $body = [
            'client_id' => $config['user_id'],
            'client_secret' => $config['key'],
            'grant_type' => 'client_credentials',
        ];

        return $this->sendJsonRequest('POST', $endpoint, $body, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
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
            'Authorization: Bearer ' . $token,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    private function downloadLabels(array $config, string $token, CarrierContext $context, array $options): array
    {
        $labelUrl = sprintf(
            '%s/%s/%s',
            $config['endpoint_label'],
            $config['store_id'],
            $context->getOrderId()
        );

        $endpoint = $this->buildUrl($config['base_url'], $labelUrl);

        $labelData = $this->attemptPdfDownload($endpoint, $token);

        $labelId = Common::getUUID();
        $labelType = isset($options['label_type']) ? (string) $options['label_type'] : 'GOI_PDF';

        return [[
            'package_id' => $labelId,
            'storage_key' => $labelId,
            'tracker_code' => sprintf('TC%s-1', $labelId),
            'label_type' => $labelType,
            'pdf_content' => $labelData,
        ]];
    }

    /**
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
            'customer_firstname' => (string) ($info['firstname'] ?? ''),
            'customer_lastname' => (string) ($info['lastname'] ?? ''),
            'customer_phone' => $phone,
            'customer_email' => (string) ($info['email'] ?? ''),
            'address' => (string) ($info['address1'] ?? ''),
            'additional_address' => (string) ($info['address2'] ?? ''),
            'city' => (string) ($info['city'] ?? ''),
            'zip' => (string) ($info['postcode'] ?? ''),
            'country_code' => (string) ($info['countrycode'] ?? ''),
            'notes' => (string) ($info['notes'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return array<string, mixed>
     */
    private function buildPieces(array $info): array
    {
        $length = (float) ($info['length'] ?? 0);
        $width = (float) ($info['width'] ?? 0);
        $height = (float) ($info['height'] ?? 0);
        $volume = $length * $width * $height;

        return [
            'weight' => (float) ($info['weight'] ?? 0),
            'volume' => $volume,
            'packages' => (int) ($info['quantity'] ?? 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProducts(int $orderId): array
    {
    $order = new \Order($orderId);
    if (!\Validate::isLoadedObject($order)) {
            throw new RuntimeException(sprintf('Unable to load order %d for GOI shipment.', $orderId));
        }

        $products = $order->getProductsDetail();
        $articles = [];
        $langId = (int) $order->id_lang;

        foreach ($products as $product) {
            $productId = (int) ($product['product_id'] ?? 0);
            $quantity = (int) ($product['product_quantity'] ?? 0);
            $weight = (float) ($product['weight'] ?? 0);
            $depth = (float) ($product['depth'] ?? 0);
            $width = (float) ($product['width'] ?? 0);
            $height = (float) ($product['height'] ?? 0);
            $volume = $depth * $width * $height;

            $name = (string) ($product['product_name'] ?? '');
            if ('' === $name && $productId > 0) {
                $productModel = new \Product($productId, false, $langId);
                if (\Validate::isLoadedObject($productModel)) {
                    $name = (string) $productModel->name;
                }
            }

            $articles[] = [
                'id' => (string) $productId,
                'name' => mb_substr($name, 0, 128),
                'quantity' => $quantity,
                'volume' => $volume,
                'weight' => $weight,
            ];
        }

        return [
            'retail_price' => (float) $order->total_paid,
            'articles' => $articles,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveServices(int $typeShipmentId): array
    {
        if ($typeShipmentId <= 0) {
            return [];
        }

        $typeShipment = $this->typeShipmentRepository->find($typeShipmentId);
        if (null === $typeShipment) {
            return [];
        }

        $codes = explode(',', $typeShipment->getBusinessCode());

        return array_values(array_filter(array_map(static fn (string $code): string => trim($code), $codes)));
    }

    private function buildUrl(string $baseUrl, string $endpoint): string
    {
        if ('' === $baseUrl) {
            throw new RuntimeException('GOI base URL is not configured.');
        }

        if ('' === $endpoint) {
            throw new RuntimeException('GOI endpoint value cannot be empty.');
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $headers
     * @return array<string, mixed>
     */
    private function sendJsonRequest(string $method, string $url, array $payload, array $headers): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $result = $this->executeRequest($method, $url, $body, $headers);

        if (!str_contains(strtolower($result['content_type']), 'application/json')) {
            throw new RuntimeException(sprintf('Unexpected GOI response content type %s for %s.', $result['content_type'], $url));
        }

        try {
            $decoded = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new RuntimeException('Unable to decode GOI response payload.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected GOI response structure.');
        }

        return $decoded;
    }

    private function attemptPdfDownload(string $url, string $token): string
    {
        for ($attempt = 1; $attempt <= self::MAX_LABEL_ATTEMPTS; $attempt++) {
            $result = $this->executeRequest('GET', $url, null, [
                'Accept: application/pdf',
                'Authorization: Bearer ' . $token,
            ]);

            if (str_contains(strtolower($result['content_type']), 'application/pdf')) {
                return $result['body'];
            }

            if ($attempt === self::MAX_LABEL_ATTEMPTS) {
                throw new RuntimeException(sprintf('GOI label download did not return PDF after %d attempts. Last response: %s', self::MAX_LABEL_ATTEMPTS, $result['body']));
            }

            usleep(self::LABEL_RETRY_DELAY_MICROSECONDS);
        }

        throw new RuntimeException('GOI label download failed after maximum retries.');
    }

    /**
     * @param string[] $headers
     * @return array{status:int, content_type:string, body:string}
     */
    private function executeRequest(string $method, string $url, ?string $body, array $headers): array
    {
        $resource = curl_init();
        if (false === $resource) {
            throw new RuntimeException('Unable to initialize cURL resource for GOI request.');
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
            throw new RuntimeException(sprintf('GOI request failed: %s', $error ?: 'unknown error'));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('GOI request returned HTTP %d: %s', $status, $response));
        }

        return [
            'status' => $status,
            'content_type' => $contentType,
            'body' => $response,
        ];
    }
}
