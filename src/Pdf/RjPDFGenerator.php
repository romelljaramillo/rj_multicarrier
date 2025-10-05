<?php
/**
 * TCPDF wrapper for label generation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Pdf;

class RjPDFGenerator extends \TCPDF
{
    public const DEFAULT_FONT = 'helvetica';

    public $header;
    public $footer;
    public $pagination;
    public $content;
    public $font;

    public $font_by_lang = [
        'ja' => 'cid0jp',
        'bg' => 'freeserif',
        'ru' => 'freeserif',
        'uk' => 'freeserif',
        'mk' => 'freeserif',
        'el' => 'freeserif',
        'en' => 'dejavusans',
        'vn' => 'dejavusans',
        'pl' => 'dejavusans',
        'ar' => 'dejavusans',
        'fa' => 'dejavusans',
        'fr' => 'dejavusans',
        'ur' => 'dejavusans',
        'az' => 'dejavusans',
        'ca' => 'dejavusans',
        'gl' => 'dejavusans',
        'hr' => 'dejavusans',
        'sr' => 'dejavusans',
        'si' => 'dejavusans',
        'cs' => 'dejavusans',
        'sk' => 'dejavusans',
        'ka' => 'dejavusans',
        'he' => 'dejavusans',
        'lo' => 'dejavusans',
        'lt' => 'dejavusans',
        'lv' => 'dejavusans',
        'tr' => 'dejavusans',
        'ko' => 'cid0kr',
        'zh' => 'cid0cs',
        'tw' => 'cid0cs',
        'th' => 'freeserif',
    ];

    /**
     * @param bool $use_cache
     * @param string $orientation
     */
    public function __construct($use_cache = false, $orientation = 'P')
    {
        parent::__construct($orientation, 'mm', 'DL', true, 'UTF-8', $use_cache, false);
        $this->setRTL(\Context::getContext()->language->is_rtl);
    }

    /**
     * set the PDF encoding.
     */
    public function setEncoding($encoding): void
    {
        $this->encoding = $encoding;
    }

    /**
     * set the PDF header.
     */
    public function createHeader($header): void
    {
        $this->header = $header;
    }

    /**
     * set the PDF footer.
     */
    public function createFooter($footer): void
    {
        $this->footer = $footer;
    }

    /**
     * create the PDF content.
     */
    public function createContent($content): void
    {
        $this->content = $content;
    }

    /**
     * create the PDF pagination.
     */
    public function createPagination($pagination): void
    {
        $this->pagination = $pagination;
    }

    /**
     * Change the font.
     */
    public function setFontForLang($iso_lang): void
    {
        if (array_key_exists($iso_lang, $this->font_by_lang)) {
            $this->font = $this->font_by_lang[$iso_lang];
        } else {
            $this->font = 'dejavusans';
        }
        $this->setFont($this->font, '', PDF_FONT_SIZE_MAIN, '', false);
    }

    /**
     * @see TCPDF::Header()
     */
    public function Header(): void
    {
        $this->writeHTML($this->header);
    }

    /**
     * @see TCPDF::Footer()
     */
    public function Footer(): void
    {
        $this->writeHTML($this->footer);
        $this->FontFamily = self::DEFAULT_FONT;
        $this->writeHTML($this->pagination);
    }

    /**
     * Render HTML template.
     *
     * @param string $filename
     * @param bool|string $display true:display to user, false:save, 'I','D','S','F','E'
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    public function render($filename, $display = true)
    {
        if (empty($filename)) {
            throw new \PrestaShopException('Missing filename.');
        }

        if ($display === true) {
            $output = 'D';
        } elseif ($display === false) {
            $output = 'S';
        } elseif ($display == 'D') {
            $output = 'D';
        } elseif ($display == 'E') {
            $output = 'E';
        } elseif ($display == 'S') {
            $output = 'S';
        } elseif ($display == 'F') {
            $output = 'F';
        } else {
            $output = 'I';
        }

        return $this->output($filename, $output);
    }

    /**
     * Write a PDF page.
     */
    public function writePage(): void
    {
        $this->lastPage();
    }

    /**
     * Override of TCPDF::getRandomSeed() - getmypid() is blocked on several hosting.
     */
    protected function getRandomSeed($seed = '')
    {
        $seed .= microtime();

        if (function_exists('openssl_random_pseudo_bytes') && (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')) {
            $seed .= openssl_random_pseudo_bytes(512);
        } else {
            for ($i = 0; $i < 23; ++$i) {
                $seed .= uniqid('', true);
            }
        }

        $seed .= uniqid('', true);
        $seed .= rand();
        $seed .= __FILE__;
        $seed .= $this->bufferlen;

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $seed .= $_SERVER['REMOTE_ADDR'];
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $seed .= $_SERVER['HTTP_USER_AGENT'];
        }
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $seed .= $_SERVER['HTTP_ACCEPT'];
        }
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            $seed .= $_SERVER['HTTP_ACCEPT_ENCODING'];
        }
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $seed .= $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        }
        if (isset($_SERVER['HTTP_ACCEPT_CHARSET'])) {
            $seed .= $_SERVER['HTTP_ACCEPT_CHARSET'];
        }

        $seed .= rand();
        $seed .= uniqid('', true);
        $seed .= microtime();

        return $seed;
    }
}
