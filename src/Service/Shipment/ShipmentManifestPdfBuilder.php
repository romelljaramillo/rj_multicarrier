<?php
/**
 * Builds the PDF binary for shipment manifests.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Shipment;

use DateTimeImmutable;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentManifestView;
use Twig\Environment;

final class ShipmentManifestPdfBuilder
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function build(ShipmentManifestView $manifest): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('RJ Multicarrier');
        $pdf->SetAuthor('RJ Multicarrier');
        $pdf->SetTitle('Manifiesto de envíos');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->setFontSubsetting(true);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        $payload = [
            'manifest' => $manifest->toArray(),
            'generated_at' => (new DateTimeImmutable())->format('Y-m-d H:i'),
        ];

        $html = $this->twig->render('@Modules/rj_multicarrier/views/templates/admin/shipment/manifest_pdf.html.twig', $payload);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('manifest.pdf', 'S');
    }
}
