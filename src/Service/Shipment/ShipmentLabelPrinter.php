<?php
/**
 * Streams merged PDFs for a shipment while updating label metadata.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Shipment;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentLabelException;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\Label;
use Roanja\Module\RjMulticarrier\Entity\Shipment;
use Roanja\Module\RjMulticarrier\Repository\LabelRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Support\Common;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentLabelPrinter
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
        private readonly LabelRepository $labelRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function streamShipmentLabels(int $shipmentId): Response
    {
        $shipment = $this->shipmentRepository->find($shipmentId);

        if (!$shipment instanceof Shipment || $shipment->isDeleted()) {
            throw ShipmentNotFoundException::withId($shipmentId);
        }

        $labelSummaries = $this->labelRepository->findPdfSummariesByShipmentId($shipmentId);
        if (empty($labelSummaries)) {
            throw ShipmentLabelException::missing();
        }

        $pdfPaths = [];
        $labelsToPersist = [];

        foreach ($labelSummaries as $summary) {
            $labelId = (int) $summary['id'];
            $label = $this->labelRepository->find($labelId);
            if (!$label instanceof Label) {
                continue;
            }

            $storageKey = $this->resolveStorageKey($summary, $label);
            $filePath = $this->resolvePdfPath($storageKey);

            if (!is_file($filePath)) {
                $filePath = $this->rebuildLabelFile($summary, $label, $storageKey);
            }

            if (!is_file($filePath)) {
                throw ShipmentLabelException::corrupt((string) ($storageKey ?? ($summary['package_id'] ?? $labelId)));
            }

            $label->setPrinted(true);
            $pdfPaths[] = $filePath;
            $labelsToPersist[] = $label;
        }

        foreach ($labelsToPersist as $label) {
            $this->entityManager->persist($label);
        }
        $this->entityManager->flush();

        $mergedPdf = Common::mergePdf($pdfPaths);
        $fileName = sprintf('shipment-%d.pdf', $shipment->getId() ?? $shipmentId);

        return new Response(
            $mergedPdf,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'public',
                'Content-Disposition' => sprintf('inline; filename="%s"', $fileName),
            ]
        );
    }

    private function resolveStorageKey(array $summary, Label $label): ?string
    {
        $pdfField = $label->getPdf();
        if (null !== $pdfField && '' !== $pdfField) {
            return $pdfField;
        }

        if (!empty($summary['pdf']) && !$this->looksLikeBase64((string) $summary['pdf'])) {
            return (string) $summary['pdf'];
        }

        if (!empty($summary['package_id'])) {
            return (string) $summary['package_id'];
        }

        return null;
    }

    private function resolvePdfPath(?string $storageKey): string
    {
        return null === $storageKey || '' === $storageKey
            ? ''
            : Common::getFileLabel($storageKey);
    }

    private function rebuildLabelFile(array $summary, Label $label, ?string $currentKey): string
    {
        $rawPdf = $summary['pdf'] ?? null;
        if (!$this->looksLikeBase64(is_string($rawPdf) ? $rawPdf : null)) {
            return '';
        }

        $decoded = base64_decode((string) $rawPdf, true);
        if (false === $decoded || '' === $decoded) {
            return '';
        }

        $storageKey = $currentKey;
        if (null === $storageKey || '' === $storageKey) {
            $storageKey = !empty($summary['package_id'])
                ? (string) $summary['package_id']
                : Common::getUUID();
        }

        if (!Common::createFileLabel($decoded, $storageKey)) {
            throw ShipmentLabelException::corrupt($storageKey);
        }

        $label->setPdf($storageKey);

        return Common::getFileLabel($storageKey);
    }

    private function looksLikeBase64(?string $value): bool
    {
        if (null === $value) {
            return false;
        }

        $value = trim($value);
        if ('' === $value || strlen($value) < 40) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9+\/\r\n=]+$/', $value);
    }
}
