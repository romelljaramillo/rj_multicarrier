<?php
/**
 * PDF facade used for label generation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Pdf;

use Module;

class RjPDF extends Module
{
    public const TEMPLATE_LABEL = 'Default';

    public $filename;
    public $pdf_renderer;
    public $shipment;
    public $shortname_company;
    public $template;
    public $num_package;
    public $send_bulk_flag = false;

    // barcodes in page
    public $cb = 0; // counter barcodes in page
    public $incH = 88;
    public $offset = 7;

    /**
     * @param string $shortname_company
     * @param array $shipment
     * @param string $template
     * @param int $num_package
     * @param string $orientation
     */
    public function __construct($shortname_company, $shipment, $template, $num_package, $orientation = 'P')
    {
        $this->pdf_renderer = new RjPDFGenerator((bool) \Configuration::get('PS_PDF_USE_CACHE'), $orientation);
        $this->template = $template;
        $this->num_package = $num_package;

        $this->shipment = $shipment;
        $this->shortname_company = $shortname_company;
    }

    /**
     * Render PDF.
     *
     * @param bool|string $display
     *
     * @return mixed
     *
     * @throws \PrestaShopException
     */
    public function render($display)
    {
        $this->pdf_renderer->setFontForLang(\Context::getContext()->language->iso_code);
        $this->pdf_renderer->startPageGroup();

        $template = $this->getTemplateObject();

        if (empty($this->filename)) {
            $this->filename = $template->getFilename();
        }

        $this->pdf_renderer->SetHeaderMargin(5);
        $this->pdf_renderer->SetFooterMargin(5);
        $this->pdf_renderer->setMargins(5, 5, 5);
        $this->pdf_renderer->AddPage();
        $this->pdf_renderer->setCellPaddings(1, 1, 1, 1);
        $this->pdf_renderer->setCellMargins(1, 1, 1, 1);

        $template->getContent();

        $this->pdf_renderer->writePage();
        unset($template);

        // clean the output buffer
        if (ob_get_level() && ob_get_length() > 0) {
            ob_clean();
        }

        return $this->pdf_renderer->render($this->filename, $display);
    }

    /**
     * Get correct PDF template classes.
     *
     * @return TemplateLabel|false
     *
     * @throws \PrestaShopException
     */
    public function getTemplateObject()
    {
        $class = false;
        $class_name = __NAMESPACE__ . '\\TemplateLabel' . $this->template;

        if (class_exists($class_name)) {
            $class = new $class_name($this->shipment, $this->pdf_renderer, $this->num_package);

            if (!($class instanceof TemplateLabel)) {
                throw new \PrestaShopException('Invalid class. It should be an instance of TemplateLabel');
            }
        }

        return $class;
    }
}
