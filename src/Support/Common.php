<?php
/**
 * Shared helper methods reused across the module.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Support;

use Context;
use Ramsey\Uuid\Uuid;
use Tools;
use iio\libmergepdf\Merger;

final class Common
{
    public static function encrypt(string $action, string $pass): string
    {
        $salt = base64_decode(_COOKIE_KEY_);
        $salt1 = hash('sha256', $salt);
        $salt2 = substr(hash('sha256', $salt), 0, 16);

        if ('encrypt' === $action) {
            return base64_encode(openssl_encrypt($pass, 'AES-256-CBC', $salt1, 0, $salt2));
        }

        if ('decrypt' === $action) {
            return (string) openssl_decrypt(base64_decode($pass), 'AES-256-CBC', $salt1, 0, $salt2);
        }

        return $pass;
    }

    public static function convertAndFormatPrice(float $price, $currency = false, ?Context $context = null): string
    {
        $context ??= Context::getContext();
        $currency = $currency ?: $context->currency;

        $locale = Tools::getContextLocale($context);

        return $locale->formatPrice(Tools::convertPrice($price, $currency), $currency->iso_code);
    }

    public static function convertAndFormatNumber(float $number): string
    {
        $context = Context::getContext();
        return Tools::getContextLocale($context)->formatNumber($number);
    }

    public static function getUUID(): string
    {
        return Uuid::uuid4()->toString();
    }

    public static function mergePdf(array $pdfList): string
    {
        $merger = new Merger();
        $merger->addIterator($pdfList);

        return $merger->merge();
    }

    public static function createFileLabel(string $pdfContent, string $labelId): bool
    {
        $labelsDir = self::getLabelsDir();

        if (!is_dir($labelsDir) && !@mkdir($labelsDir, 0775, true) && !is_dir($labelsDir)) {
            return false;
        }

        file_put_contents($labelsDir . $labelId . '.pdf', $pdfContent);

        return true;
    }

    public static function getFileLabel(string $labelId): string
    {
        return self::getLabelsDir() . $labelId . '.pdf';
    }

    private static function getLabelsDir(): string
    {
        return defined('RJ_MULTICARRIER_LABEL_DIR')
            ? rtrim(RJ_MULTICARRIER_LABEL_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            : _PS_MODULE_DIR_ . 'rj_multicarrier/labels/';
    }
}
