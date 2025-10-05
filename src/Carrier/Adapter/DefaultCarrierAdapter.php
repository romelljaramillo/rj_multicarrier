<?php
/**
 * Fallback carrier adapter used when no specific integration is available.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

use Roanja\Module\RjMulticarrier\Pdf\RjPDF;
use Roanja\Module\RjMulticarrier\Support\Common;

final class DefaultCarrierAdapter implements CarrierAdapterInterface
{
    public function getCode(): string
    {
        return 'DEFAULT';
    }

    public function generateShipment(CarrierContext $context): CarrierGenerationResult
    {
        $payload = $context->getPayload();
        $options = $context->getOptions();

        $shipmentNumber = $payload['num_shipment'] ?? $context->getShipmentNumber();
        $payload['num_shipment'] = $shipmentNumber;

        $labelType = isset($options['label_type']) ? (string) $options['label_type'] : 'B2X_Generic_A4_Third';
        $displayPdf = $options['display_pdf'] ?? 'S';
        $shortname = isset($options['shortname']) ? (string) $options['shortname'] : $context->getCarrierCode();

        $packagesQty = (int) ($payload['info_package']['quantity'] ?? 0);
        $labels = [];

        for ($packageIndex = 1; $packageIndex <= $packagesQty; $packageIndex++) {
            $rjpdf = new RjPDF($shortname, $payload, RjPDF::TEMPLATE_LABEL, $packageIndex);
            $pdfContent = $rjpdf->render($displayPdf);

            if (empty($pdfContent)) {
                continue;
            }

            $labelId = Common::getUUID();
            $labels[] = [
                'package_id' => $labelId,
                'storage_key' => $labelId,
                'tracker_code' => sprintf('TC%s-%d', $labelId, $packageIndex),
                'label_type' => $labelType,
                'pdf_content' => $pdfContent,
            ];
        }

        return new CarrierGenerationResult(
            (string) $shipmentNumber,
            $payload,
            isset($payload['response']) && is_array($payload['response']) ? $payload['response'] : null,
            $labels
        );
    }
}
