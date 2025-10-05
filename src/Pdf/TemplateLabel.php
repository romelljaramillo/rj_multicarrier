<?php
/**
 * Base template for carrier labels.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Pdf;

use Configuration;
use Context;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Roanja\Module\RjMulticarrier\Support\Common;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use RuntimeException;
use Tools;
use Validate;

abstract class TemplateLabel
{
    public $title;
    public $date;
    public $available_in_your_account = true;

    /** @var Shop */
    public $shop;

    public $shipment;
    public $response;

    public $company_shortname;
    public $pdf_class;
    public $num_package;
    protected $datosResultado;
    protected $cod_package;

    // barcodes in page
    public $cb = 0; // counter barcodes in page
    public $incH = 88;
    public $offset = 7;

    private static ?CompanyRepository $companyRepository = null;

    private static ?TypeShipmentRepository $typeShipmentRepository = null;

    public function __construct($shipment, $pdf_class, $num_package = '')
    {
        $this->shipment = $shipment;

        if (isset($this->shipment['response'])) {
            $this->response = $this->shipment['response'];
            $this->datosResultado = $this->response->datosResultado ?? null;
            $this->cod_package = $this->response->listaBultos[$num_package - 1] ?? null;
        }

        $this->pdf_class = $pdf_class;
        $this->num_package = $num_package;
    }

    /**
     * Returns the template's HTML header.
     */
    public function getHeader(): void
    {
        $this->pdf_class->SetHeaderData(
            PDF_HEADER_LOGO,
            PDF_HEADER_LOGO_WIDTH,
            PDF_HEADER_TITLE,
            PDF_HEADER_STRING,
            [0, 64, 255],
            [0, 64, 128]
        );
        $this->pdf_class->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
    }

    /**
     * Returns the template's HTML footer.
     */
    public function getFooter(): void
    {
        $this->pdf_class->setFooterData([0, 64, 0], [0, 64, 128]);
        $this->pdf_class->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
    }

    protected function zoneCarrier(): void
    {
        $this->pdf_class->SetFillColor(255, 255, 255);
        $this->pdf_class->setY(5 + ($this->incH * $this->cb) + ($this->offset * $this->cb));
        $this->pdf_class->MultiCell(25, 0, $this->l('Trasnporte'), 0, 'L', 1, 0, '', '', true);
        $this->pdf_class->SetFont('dejavusans', '', 14, '', true);
        $this->pdf_class->MultiCell(70, 0, $this->shipment['name_carrier'], 0, 'R', 0, 1, '', '', true);
        $this->pdf_class->Line(5, 15, 105, 15);
        $this->pdf_class->ln(4);
    }

    protected function zoneShipper(): void
    {
        $this->pdf_class->SetFont('dejavusans', '', 9, '', true);
        $this->pdf_class->setY(15);
        $this->pdf_class->MultiCell(10, 0, $this->l('From') . ':', 0, 'L', 1, 0, '', '', true);

        $data_shipper = $this->shipment['info_shop']['company'] . "\n"
            . $this->shipment['info_shop']['lastname'] . ' ' . $this->shipment['info_shop']['firstname'] . "\n"
            . $this->shipment['info_shop']['street'] . ' - ' . $this->shipment['info_shop']['city'] . "\n"
            . $this->shipment['info_shop']['state'] . ' - ' . $this->shipment['info_shop']['country'] . "\n"
            . $this->shipment['info_shop']['email'] . ' - ' . $this->shipment['info_shop']['phone'];

        $this->pdf_class->MultiCell(80, 0, $data_shipper, 0, 'L', 0, 1, '', '', true);
        $this->pdf_class->Line(5, 40, 105, 40);
        $this->pdf_class->ln();
    }

    protected function zoneReceiver(): void
    {
        $this->pdf_class->setY(45);
        $this->pdf_class->SetFont('dejavusans', '', 9, '', true);

        $style4 = [
            'L' => ['width' => 1, 'cap' => 'round', 'join' => 'miter', 'dash' => 0, 'color' => [0, 0, 0]],
            'T' => ['width' => 1, 'cap' => 'butt', 'join' => 'miter', 'dash' => '30,245', 'phase' => 10, 'color' => [0, 0, 0]],
            'R' => ['width' => 1, 'cap' => 'round', 'join' => 'miter', 'dash' => 0, 'color' => [0, 0, 0]],
            'B' => ['width' => 1, 'cap' => 'butt', 'join' => 'miter', 'dash' => '30,245', 'phase' => 10, 'color' => [0, 0, 0]],
        ];

        $this->pdf_class->Rect(5, 45, 100, 40, 'DF', $style4);

        $this->pdf_class->MultiCell(10, 0, $this->l('To') . ':', 0, 'L', 1, 0, '', '', true);

        $data_shipper = $this->shipment['info_customer']['company'] . "\n"
            . $this->shipment['info_customer']['lastname'] . ' ' . $this->shipment['info_customer']['firstname'] . "\n"
            . $this->l('Tel.') . ': ' . $this->shipment['info_customer']['phone'] . ' - ' . $this->shipment['info_customer']['phone_mobile'];

        $this->pdf_class->MultiCell(80, 0, $data_shipper, 0, 'L', 0, 1, '', '', true);

        $this->pdf_class->setCellPaddings(0, 0, 0, 0);
        $this->pdf_class->setCellMargins(14, 0, 0, 0);

        $this->pdf_class->SetFont('dejavusans', '', 10, '', true);
        $this->pdf_class->Cell(50, 0, $this->shipment['info_customer']['address1'], 0, 1, 'L');
        $this->pdf_class->SetFont('dejavusans', '', 14, '', true);
        $this->pdf_class->Cell(50, 0, $this->shipment['info_customer']['postcode'] . ' - ' . $this->shipment['info_customer']['city'], 0, 1, 'L');
        $this->pdf_class->SetFont('dejavusans', '', 10, '', true);
        $this->pdf_class->Cell(50, 0, $this->shipment['info_customer']['state'] . ' - ' . $this->shipment['info_customer']['country'], 0, 1, 'L');
    }

    protected function zonePackage(): void
    {
        $this->pdf_class->setCellPaddings(1, 1, 1, 1);
        $this->pdf_class->setCellMargins(1, 1, 1, 1);
        $style2 = ['width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [0, 0, 0]];

        $this->pdf_class->setY(90);

        $this->pdf_class->Line(5, 90, 105, 90, $style2);

        $this->pdf_class->SetFont('dejavusans', '', 7, '', true);

        $this->pdf_class->Cell(25, 0, $this->l('pedido'), 0, 0, 'C', 1);
        $this->pdf_class->Cell(20, 0, $this->l('weight'), 0, 0, 'C', 1);
        $this->pdf_class->Cell(20, 0, $this->l('packages'), 0, 0, 'C', 1);
        $this->pdf_class->Cell(25, 0, $this->l('cash on delivery'), 0, 0, 'C', 1);

        $this->pdf_class->ln();

        $this->pdf_class->SetFont('dejavusans', '', 11, '', true);

        $id_order = $this->shipment['info_package']['id_order'];
        $cash_ondelivery = Common::convertAndFormatPrice($this->shipment['info_package']['cash_ondelivery']);
        $quantity = $this->shipment['info_package']['quantity'];
        $weight = $this->shipment['info_package']['weight'] / $quantity;
        $weight = Common::convertAndFormatNumber($weight);

        $this->pdf_class->Cell(25, 0, $id_order, 0, 0, 'C', 1);
        $this->pdf_class->Cell(20, 0, $weight, 0, 0, 'C', 1);
        $this->pdf_class->Cell(20, 0, $this->num_package . '/' . $quantity, 0, 0, 'C', 1);
        $this->pdf_class->Cell(25, 0, $cash_ondelivery, 0, 0, 'C', 1);

        $this->pdf_class->Line(30, 92, 30, 103, $style2);
        $this->pdf_class->Line(55, 92, 55, 103, $style2);
        $this->pdf_class->Line(75, 92, 75, 103, $style2);

        $this->pdf_class->Line(5, 105, 105, 105, $style2);
        $this->pdf_class->ln(4);
    }

    protected function zoneInfoShipment(): void
    {
        $style2 = ['width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [0, 0, 0]];

        $this->pdf_class->setY(105);

        $this->pdf_class->SetFont('dejavusans', '', 9, '', true);
        $this->pdf_class->setCellMargins(0, 1, 0, 1);
        $this->pdf_class->setCellPaddings(0, 10, 0, 0);

    $typeShipmentId = (int) ($this->shipment['info_package']['id_type_shipment'] ?? 0);
    $typeShipmentName = self::resolveTypeShipmentName($typeShipmentId);

        $this->pdf_class->Cell(10, 0, 'ES', 0, 0, 'C', 1);
        $this->pdf_class->Cell(25, 0, $typeShipmentName, 0, 0, 'C', 1);
    $this->pdf_class->Cell(60, 0, 'Env.: ' . ($this->datosResultado ?? ''), 0, 0, 'C', 1);

        $this->pdf_class->Line(15, 107, 15, 118, $style2);
        $this->pdf_class->Line(45, 107, 45, 118, $style2);

        $this->pdf_class->Line(5, 120, 105, 120, $style2);
        $this->pdf_class->ln();
    }

    protected function zoneBarcode(): void
    {
        if (!$this->cod_package) {
            return;
        }

        $this->pdf_class->setCellPaddings(1, 1, 1, 1);

        $style = [
            'position' => '',
            'align' => 'C',
            'stretch' => true,
            'fitwidth' => false,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'text' => false,
            'font' => 'freeserif',
            'fontsize' => 8,
            'stretchtext' => 4,
        ];
        $style2 = ['width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [0, 0, 0]];

        $this->pdf_class->SetFont('dejavusans', '', 11, '', true);
        $this->pdf_class->write1DBarcode($this->cod_package->codUnico, 'C128', 6, 125, 98, 26, 0.4, $style, 'C');
        $this->pdf_class->ln();
        $this->pdf_class->setY(145);
    $this->pdf_class->Cell(100, 0, 'Pack.: ' . $this->cod_package->codUnico, 0, 1, 'C');
        $this->pdf_class->Line(5, 155, 105, 155, $style2);
    }

    /**
     * Returns the shop address.
     */
    protected function getShopAddress(): string
    {
        $shop_address = '';

        $shop_address_obj = $this->shop->getAddress();
        if (isset($shop_address_obj) && class_exists('AddressFormat') && is_object($shop_address_obj)) {
            $shop_address = \AddressFormat::generateAddress($shop_address_obj, [], ' - ', ' ');
        }

        return $shop_address;
    }

    /**
     * Returns the invoice logo.
     */
    protected function getLogo(): string
    {
        $logo = '';

        $icon = self::getIconCompanyByShortname($this->company_shortname);

        if ($icon && file_exists(IMG_ICON_COMPANY_DIR . $this->company_shortname . '/' . $icon)) {
            $logo = IMG_ICON_COMPANY_DIR . $this->company_shortname . '/' . $icon;
        } elseif ($icon && file_exists(IMG_ICON_COMPANY_DIR . $icon)) {
            $logo = IMG_ICON_COMPANY_DIR . $icon;
        }

        return $logo;
    }

    /**
     * Returns the template's HTML content.
     */
    public function getContent(): void
    {
        $this->zoneCarrier();
        $this->zoneShipper();
        $this->zoneReceiver();
        $this->zonePackage();

        if (isset($this->datosResultado)) {
            $this->zoneInfoShipment();
        }

        if (isset($this->cod_package)) {
            $this->zoneBarcode();
        }
    }

    /**
     * Returns the template filename.
     */
    public function getFilename(): string
    {
        $format = '%1$s%2$06d';

        $context = Context::getContext();
        $shopId = isset($context->shop) ? (int) $context->shop->id : null;
        $shopGroupId = isset($context->shop) ? (int) $context->shop->id_shop_group : null;

        $prefix = '';
        if (class_exists('Configuration')) {
            $prefix = (string) call_user_func(['Configuration', 'get'], 'RJ_ETIQUETA_TRANSP_PREFIX', null, $shopGroupId, $shopId);
        }

        return sprintf(
            $format,
            $prefix,
            $this->shipment['id_order'],
            date('Y')
        ) . '.pdf';
    }

    /**
     * Translation method.
     */
    protected static function l($string): string
    {
        $context = Context::getContext();
        if (method_exists($context, 'getTranslator')) {
            return $context->getTranslator()->trans($string, [], 'Modules.Rjmulticarrier.Pdf');
        }

        return $string;
    }

    /**
     * Returns the template's HTML pagination block.
     */
    public function getPagination(): void
    {
        // legacy template does not render extra pagination block
    }

    private static function getCompanyRepository(): CompanyRepository
    {
        if (!self::$companyRepository instanceof CompanyRepository) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $repository = $container->get(CompanyRepository::class);

            if (!$repository instanceof CompanyRepository) {
                throw new RuntimeException('Unable to resolve CompanyRepository service');
            }

            self::$companyRepository = $repository;
        }

        return self::$companyRepository;
    }

    private static function getTypeShipmentRepository(): TypeShipmentRepository
    {
        if (!self::$typeShipmentRepository instanceof TypeShipmentRepository) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $repository = $container->get(TypeShipmentRepository::class);

            if (!$repository instanceof TypeShipmentRepository) {
                throw new RuntimeException('Unable to resolve TypeShipmentRepository service');
            }

            self::$typeShipmentRepository = $repository;
        }

        return self::$typeShipmentRepository;
    }

    private static function resolveTypeShipmentName(int $typeShipmentId): string
    {
        if ($typeShipmentId <= 0) {
            return '';
        }

        try {
            $typeShipment = self::getTypeShipmentRepository()->find($typeShipmentId);
        } catch (RuntimeException $exception) {
            return '';
        }

        return $typeShipment instanceof TypeShipment ? $typeShipment->getName() : '';
    }

    private static function getIconCompanyByShortname(string $shortname): ?string
    {
        if ('' === trim($shortname)) {
            return null;
        }

        try {
            $company = self::getCompanyRepository()->findOneByShortName($shortname);
        } catch (RuntimeException $exception) {
            return null;
        }

        return $company?->getIcon();
    }
}
