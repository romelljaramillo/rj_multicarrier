<?php
/**
 * Carrier adapter for GLS integration (ASMRed SOAP service).
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use Exception;
use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use PrestaShop\PrestaShop\Adapter\Country\CountryDataProvider;
use Roanja\Module\RjMulticarrier\Support\Common;
use RuntimeException;
use SimpleXMLElement;

final class GlsCarrierAdapter implements CarrierAdapterInterface
{
    private const CODE = 'GLS';
    private const NAMESPACE_URI = 'http://www.asmred.com/';

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

        $shipmentNumber = $payload['num_shipment'] ?? $context->getShipmentNumber();
        if ('' === (string) $shipmentNumber) {
            $shipmentNumber = Common::getUUID();
        }

        $payload['num_shipment'] = (string) $shipmentNumber;

        $shipmentPayload = $this->buildShipmentPayload($context, $payload, $config);
        $requestXml = $this->buildShipmentXml($shipmentPayload);

        $rawResponse = $this->sendXmlRequest($config['endpoint_url'], $requestXml);
        [$responsePayload, $labels] = $this->parseShipmentResponse($rawResponse, $options);

        if (empty($labels)) {
            throw new RuntimeException('GLS response did not include any printable labels.');
        }

        $requestSnapshot = $payload;
        $requestSnapshot['gls_request_xml'] = $requestXml;

        return new CarrierGenerationResult(
            (string) $shipmentNumber,
            $requestSnapshot,
            $responsePayload,
            $labels
        );
    }

    public static function getDefaultConfiguration(): array
    {
        return [
            [
                'name' => 'GLS_ENV',
                'label' => 'Modo producción',
                'value' => '0',
                'required' => true,
                'legacy' => ['RJ_GLS_ENV'],
            ],
            [
                'name' => 'GLS_GUID',
                'label' => 'GUID producción',
                'required' => true,
                'legacy' => ['RJ_GLS_GUID'],
            ],
            [
                'name' => 'GLS_GUID_DEV',
                'label' => 'GUID pruebas',
                'required' => true,
                'legacy' => ['RJ_GLS_GUID_DEV'],
            ],
            [
                'name' => 'GLS_URL',
                'label' => 'Endpoint SOAP',
                'description' => 'URL base del servicio ASM/GLS.',
                'required' => true,
                'legacy' => ['RJ_GLS_URL'],
            ],
            [
                'name' => 'GLS_WEIGHT',
                'label' => 'Peso por defecto',
                'value' => null,
                'required' => true,
                'legacy' => ['RJ_GLS_WEIGHT'],
            ],
            [
                'name' => 'GLS_QUANTITY',
                'label' => 'Bultos por defecto',
                'value' => '1',
                'required' => true,
                'legacy' => ['RJ_GLS_QUANTITY'],
            ],
            [
                'name' => 'GLS_RETORNO',
                'label' => 'Retorno por defecto',
                'value' => '0',
                'required' => true,
                'legacy' => ['RJ_GLS_RETORNO'],
            ],
            [
                'name' => 'GLS_RCS',
                'label' => 'POD por defecto',
                'value' => '0',
                'required' => true,
                'legacy' => ['RJ_GLS_RCS'],
            ],
            [
                'name' => 'GLS_INCOTERM',
                'label' => 'Incoterm por defecto',
                'value' => '0',
                'required' => true,
                'legacy' => ['RJ_GLS_INCOTERM'],
            ],
            [
                'name' => 'GLS_VSEC',
                'label' => 'Valor asegurado por defecto',
                'value' => '0',
                'required' => true,
                'legacy' => ['RJ_GLS_VSEC'],
            ],
            [
                'name' => 'GLS_DORIG',
                'label' => 'Departamento origen',
                'value' => null,
                'required' => true,
                'legacy' => ['RJ_GLS_DORIG'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfiguration(): array
    {
        $isProduction = (int) $this->fetchConfigurationValue('ENV') === 1;
        $guid = $this->fetchConfigurationValue($isProduction ? 'GUID' : 'GUID_DEV');
        $endpointUrl = $this->fetchConfigurationValue('URL');

        if ('' === $guid) {
            throw new RuntimeException('GLS GUID is not configured.');
        }

        if ('' === $endpointUrl) {
            throw new RuntimeException('GLS endpoint URL is not configured.');
        }

        return [
            'guid' => $guid,
            'endpoint_url' => $endpointUrl,
            'default_weight' => $this->toFloat($this->fetchConfigurationValue('WEIGHT')),
            'default_quantity' => max(1, (int) $this->fetchConfigurationValue('QUANTITY', '1')),
            'default_retorno' => (int) $this->fetchConfigurationValue('RETORNO', '0'),
            'default_rcs' => (bool) ((int) $this->fetchConfigurationValue('RCS', '0')),
            'default_incoterm' => (int) $this->fetchConfigurationValue('INCOTERM', '0'),
            'default_vsec' => $this->toFloat($this->fetchConfigurationValue('VSEC', '0')),
            'default_dorig' => $this->fetchConfigurationValue('DORIG'),
        ];
    }

    private function fetchConfigurationValue(string $suffix, ?string $default = null): string
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

        return $default ?? '';
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
        $orderReference = $context->getOrderReference();

        $infoCustomer = (array) ($payload['info_customer'] ?? []);
        $Configuration = (array) ($payload['configuration_shop'] ?? []);
        $infoPackage = (array) ($payload['info_package'] ?? []);
        $infoTypeShipment = (array) ($payload['info_type_shipment'] ?? []);
        $configExtra = (array) ($payload['config_extra_info'] ?? []);

        $serviceMeta = $this->resolveServiceMetadata($infoTypeShipment);

        $quantity = (int) ($infoPackage['quantity'] ?? 0);
        if ($quantity <= 0) {
            $quantity = (int) ($configExtra['quantity'] ?? $config['default_quantity']);
        }
        $quantity = max(1, $quantity);

        $weight = $this->toFloat($infoPackage['weight'] ?? null);
        if ($weight <= 0.0) {
            $weight = $config['default_weight'] > 0 ? $config['default_weight'] : 1.0;
        }

        $retorno = $infoPackage['retorno'] ?? $configExtra['retorno'] ?? $config['default_retorno'];
        $retorno = (int) $retorno;

        $rcs = $infoPackage['rcs'] ?? $configExtra['rcs'] ?? ($config['default_rcs'] ? 1 : 0);
        $rcs = (int) $rcs;

        $incoterm = (int) ($infoPackage['incoterm'] ?? $configExtra['incoterm'] ?? $config['default_incoterm']);
        $insuredValue = $this->toFloat($infoPackage['vsec'] ?? $configExtra['vsec'] ?? $config['default_vsec']);
        $departmentOrigin = (string) ($infoPackage['dorig'] ?? $configExtra['dorig'] ?? $config['default_dorig']);

        $cashOnDelivery = $this->toFloat($infoPackage['cash_ondelivery'] ?? 0.0);
        $message = (string) ($infoPackage['message'] ?? '');
        $infoCustomer['message'] = $message;

        $receiver = $this->buildReceiver($infoCustomer);
        $shipper = $this->buildShipper($Configuration, $departmentOrigin);

        $totalWeight = $weight * $quantity;

        return [
            'guid' => $config['guid'],
            'date' => (new DateTimeImmutable())->format('d/m/Y'),
            'shipment_number' => (string) ($payload['num_shipment'] ?? Common::getUUID()),
            'order_id' => $orderId,
            'order_reference' => $orderReference,
            'receiver' => $receiver,
            'shipper' => $shipper,
            'service' => [
                'code' => $serviceMeta['service'],
                'schedule' => $serviceMeta['schedule'],
                'bultos' => $quantity,
                'weight' => $totalWeight,
                'retorno' => $retorno,
                'incoterm' => $incoterm,
                'pod' => $rcs > 0 ? 'S' : 'N',
                'portes' => 'P',
            ],
            'cash_on_delivery' => $cashOnDelivery,
            'insured_value' => $insuredValue,
            'message' => $message,
            'references' => [
                'primary' => sprintf('%010d', $orderId),
                'customer' => $orderReference,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $infoTypeShipment
     * @return array{service:int,schedule:int}
     */
    private function resolveServiceMetadata(array $infoTypeShipment): array
    {
        $serviceName = strtoupper((string) ($infoTypeShipment['name'] ?? ''));

        $mapping = [
            'GLS10' => ['service' => 1, 'schedule' => 0],
            'GLS14' => ['service' => 1, 'schedule' => 2],
            'GLS24' => ['service' => 1, 'schedule' => 3],
            'BUSPAR' => ['service' => 96, 'schedule' => 18],
            'ECONOMY' => ['service' => 37, 'schedule' => 18],
            'EUROBUSINESSPARCEL' => ['service' => 74, 'schedule' => 3],
        ];

        if (isset($mapping[$serviceName])) {
            return $mapping[$serviceName];
        }

        // fallback to GLS24 as standard national delivery
        return $mapping['GLS24'];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function buildReceiver(array $info): array
    {
        $countryId = (int) ($info['id_country'] ?? 0);
        $countryIso = $countryId > 0 ? strtoupper((string) $this->countryDataProvider->getIsoCodebyId($countryId)) : '';

        $phone = $this->normalizePhone((string) ($info['phone'] ?? ''));
        $mobile = $this->normalizePhone((string) ($info['phone_mobile'] ?? ''));

        if ('' === $phone) {
            $phone = $mobile;
        }
        if ('' === $mobile) {
            $mobile = $phone;
        }

        $vatNumber = (string) ($info['vat_number'] ?? ($info['dni'] ?? ''));

        return [
            'country_iso' => $countryIso,
            'name' => trim(((string) ($info['firstname'] ?? '')) . ' ' . ((string) ($info['lastname'] ?? ''))),
            'address1' => (string) ($info['address1'] ?? ''),
            'city' => (string) ($info['city'] ?? ''),
            'state' => (string) ($info['state'] ?? ''),
            'postcode' => (string) ($info['postcode'] ?? ''),
            'phone' => $phone,
            'mobile' => $mobile,
            'email' => (string) ($info['email'] ?? ''),
            'message' => (string) ($info['message'] ?? ''),
            'vat_number' => $vatNumber,
        ];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    private function buildShipper(array $info, string $departmentOrigin): array
    {
        $countryId = (int) ($info['id_country'] ?? 0);
        $countryIso = $countryId > 0 ? strtoupper((string) $this->countryDataProvider->getIsoCodebyId($countryId)) : '';

        return [
            'country_iso' => $countryIso,
            'company' => (string) ($info['company'] ?? ''),
            'street' => (string) ($info['street'] ?? ''),
            'city' => (string) ($info['city'] ?? ''),
            'state' => (string) ($info['state'] ?? ''),
            'postcode' => (string) ($info['postcode'] ?? ''),
            'email' => (string) ($info['email'] ?? ''),
            'phone' => (string) ($info['phone'] ?? ''),
            'department' => $departmentOrigin,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildShipmentXml(array $payload): string
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = false;

        $servicesNode = $doc->createElementNS(self::NAMESPACE_URI, 'Servicios');
        $servicesNode->setAttribute('uidcliente', $payload['guid']);
        $doc->appendChild($servicesNode);

        $envio = $doc->createElement('Envio');
        $envio->setAttribute('codbarras', '');
        $servicesNode->appendChild($envio);

        $this->appendTextElement($doc, $envio, 'Fecha', $payload['date']);
        $this->appendTextElement($doc, $envio, 'Portes', $payload['service']['portes']);
        $this->appendTextElement($doc, $envio, 'Servicio', (string) $payload['service']['code']);
        $this->appendTextElement($doc, $envio, 'Horario', (string) $payload['service']['schedule']);
        $this->appendTextElement($doc, $envio, 'Bultos', (string) $payload['service']['bultos']);
        $this->appendTextElement($doc, $envio, 'Peso', (string) $this->formatNumber($payload['service']['weight']));
        $this->appendTextElement($doc, $envio, 'Retorno', (string) $payload['service']['retorno']);
        $this->appendTextElement($doc, $envio, 'Pod', (string) $payload['service']['pod']);

        if ((int) $payload['service']['incoterm'] > 0) {
            $aduanas = $doc->createElement('Aduanas');
            $this->appendTextElement($doc, $aduanas, 'Incoterm', (string) $payload['service']['incoterm']);
            $envio->appendChild($aduanas);
        }

        $envio->appendChild($this->buildShipperNode($doc, $payload['shipper']));
        $envio->appendChild($this->buildReceiverNode($doc, $payload['receiver']));

        $referencesNode = $doc->createElement('Referencias');
        $referencesNode->appendChild($this->createCdataReference($doc, '0', $payload['references']['primary']));
        if (!empty($payload['references']['customer'])) {
            $referencesNode->appendChild($this->createCdataReference($doc, 'C', (string) $payload['references']['customer']));
        }
        $envio->appendChild($referencesNode);

        if ($payload['cash_on_delivery'] > 0) {
            $importes = $doc->createElement('Importes');
            $this->appendTextElement($doc, $importes, 'Reembolso', $this->formatNumber($payload['cash_on_delivery']));
            $envio->appendChild($importes);
        }

        if ($payload['insured_value'] > 0) {
            $seguro = $doc->createElement('Seguro');
            $seguro->setAttribute('tipo', '1');
            $this->appendTextElement($doc, $seguro, 'Descripcion', '');
            $this->appendTextElement($doc, $seguro, 'Importe', $this->formatNumber($payload['insured_value']));
            $envio->appendChild($seguro);
        }

        $devuelve = $doc->createElement('DevuelveAdicionales');
        $labelNode = $doc->createElement('Etiqueta');
        $labelNode->setAttribute('tipo', 'PDF');
        $devuelve->appendChild($labelNode);
        $envio->appendChild($devuelve);

        $xml = $doc->saveXML();
        if (false === $xml) {
            throw new RuntimeException('Unable to generate GLS XML payload.');
        }

        return $xml;
    }

    private function appendTextElement(DOMDocument $doc, DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);
    }

    private function createCdataReference(DOMDocument $doc, string $type, string $value): DOMElement
    {
        $reference = $doc->createElement('Referencia');
        $reference->setAttribute('tipo', $type);
        $reference->appendChild($doc->createCDATASection($value));

        return $reference;
    }

    /**
     * @param array<string, mixed> $shipper
     */
    private function buildShipperNode(DOMDocument $doc, array $shipper): DOMElement
    {
        $node = $doc->createElement('Remite');
        $this->appendTextElement($doc, $node, 'Plaza', '');
        $name = $doc->createElement('Nombre');
        $name->appendChild($doc->createCDATASection($shipper['company']));
        $node->appendChild($name);
        $direccion = $doc->createElement('Direccion');
        $direccion->appendChild($doc->createCDATASection($shipper['street']));
        $node->appendChild($direccion);
        $this->appendCdataElement($doc, $node, 'Poblacion', $shipper['city']);
        $this->appendCdataElement($doc, $node, 'Provincia', $shipper['state']);
        $this->appendTextElement($doc, $node, 'Pais', $this->mapCountryIsoToDial($shipper['country_iso']));
        $this->appendTextElement($doc, $node, 'CP', $shipper['postcode']);
        $this->appendCdataElement($doc, $node, 'Telefono', $shipper['phone']);
        $this->appendCdataElement($doc, $node, 'Movil', '');
        $this->appendCdataElement($doc, $node, 'Email', $shipper['email']);
        $observaciones = $doc->createElement('Observaciones');
        $observaciones->appendChild($doc->createCDATASection(''));
        $node->appendChild($observaciones);

        return $node;
    }

    /**
     * @param array<string, mixed> $receiver
     */
    private function buildReceiverNode(DOMDocument $doc, array $receiver): DOMElement
    {
        $node = $doc->createElement('Destinatario');
        $this->appendTextElement($doc, $node, 'Codigo', '');
        $this->appendTextElement($doc, $node, 'Plaza', '');
        $this->appendCdataElement($doc, $node, 'Nombre', $receiver['name']);
        $this->appendCdataElement($doc, $node, 'Direccion', $receiver['address1']);
        $this->appendCdataElement($doc, $node, 'Poblacion', $receiver['city']);
        $this->appendCdataElement($doc, $node, 'Provincia', $receiver['state']);
        $this->appendTextElement($doc, $node, 'Pais', $this->mapCountryIsoToDial($receiver['country_iso']));
        $this->appendTextElement($doc, $node, 'CP', $receiver['postcode']);
        $this->appendCdataElement($doc, $node, 'Telefono', $receiver['phone']);
        $this->appendCdataElement($doc, $node, 'Movil', $receiver['mobile']);
        $this->appendCdataElement($doc, $node, 'Email', $receiver['email']);
        $this->appendCdataElement($doc, $node, 'Observaciones', $receiver['message']);
        $att = $doc->createElement('ATT');
        $att->appendChild($doc->createCDATASection($receiver['name']));
        $node->appendChild($att);
        $this->appendTextElement($doc, $node, 'NIF', $receiver['vat_number']);

        return $node;
    }

    private function appendCdataElement(DOMDocument $doc, DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createCDATASection($value));
        $parent->appendChild($node);
    }

    private function sendXmlRequest(string $endpoint, string $body): string
    {
        $handle = curl_init();
        if (false === $handle) {
            throw new RuntimeException('Unable to initialize cURL for GLS request.');
        }

        $headers = ['Content-Type: text/xml; charset=utf-8'];

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (false === $response) {
            throw new RuntimeException(sprintf('GLS request failed: %s', $error ?: 'unknown error'));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('GLS request returned HTTP %d: %s', $status, $response));
        }

        return $response;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function parseShipmentResponse(string $rawResponse, array $options): array
    {
        $sanitized = $this->sanitizeXml($rawResponse);

        libxml_use_internal_errors(true);
        try {
            $xml = new SimpleXMLElement($sanitized);
        } catch (Exception $exception) {
            throw new RuntimeException('Unable to parse GLS response XML.', 0, $exception);
        } finally {
            libxml_clear_errors();
        }

        $responseSummary = [
            'raw_xml' => $rawResponse,
            'sanitized_xml' => $sanitized,
        ];

        $expedition = $this->extractFirstValue($xml, 'NumExpedicion') ?? $this->extractFirstValue($xml, 'CodigoBarras');
        if (null !== $expedition) {
            $responseSummary['expedition'] = $expedition;
        }

        $labels = $this->extractLabels($xml, $options);

        return [$responseSummary, $labels];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractLabels(SimpleXMLElement $xml, array $options): array
    {
        $labelType = isset($options['label_type']) ? (string) $options['label_type'] : 'GLS_PDF';

        $candidates = [];
        foreach (['Etiqueta', 'EtiquetaPDF', 'PDF', 'EtiquetaBase64'] as $tag) {
            $nodes = $xml->xpath(sprintf('//%s', $tag));
            if (false !== $nodes) {
                $candidates = array_merge($candidates, $nodes);
            }
        }

        $labels = [];
        foreach ($candidates as $index => $node) {
            $content = trim((string) $node);
            if ('' === $content) {
                continue;
            }

            $decoded = base64_decode($content, true);
            if (false === $decoded || '' === $decoded) {
                continue;
            }

            $attributes = $node->attributes();
            $labelId = (string) ($attributes['labelId'] ?? $attributes['etiquetaId'] ?? '');
            if ('' === $labelId) {
                $labelId = sprintf('%s-%d', Common::getUUID(), $index + 1);
            }

            $trackerCode = (string) ($attributes['trackerCode'] ?? $attributes['codigo'] ?? $labelId);

            $labels[] = [
                'package_id' => $labelId,
                'storage_key' => $labelId,
                'tracker_code' => $trackerCode,
                'label_type' => $labelType,
                'pdf_content' => $decoded,
            ];
        }

        return $labels;
    }

    private function sanitizeXml(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml); // remove BOM if present
        $xml = preg_replace('/xmlns(:[\w-]+)?="[^"]*"/', '', (string) $xml);
        return trim((string) $xml);
    }

    private function extractFirstValue(SimpleXMLElement $xml, string $tag): ?string
    {
        $result = $xml->xpath(sprintf('//%s', $tag));
        if (false === $result || empty($result)) {
            return null;
        }

        $value = (string) $result[0];
        return '' === trim($value) ? null : trim($value);
    }

    private function toFloat(mixed $value): float
    {
        if (null === $value) {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/[^0-9+]/', '', $value) ?? '';
    }

    private function mapCountryIsoToDial(string $iso): string
    {
        return match (strtoupper($iso)) {
            'ES' => '34',
            'PT' => '351',
            default => $iso,
        };
    }
}
