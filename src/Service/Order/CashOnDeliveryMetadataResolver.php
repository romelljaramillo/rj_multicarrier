<?php
/**
 * Resolves metadata related to cash on delivery payments for a given order.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Order;

use Context;
use Module;
use Order;
use Roanja\Module\RjMulticarrier\Entity\Configuration as SenderConfiguration;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;
use Shop;
use Validate;

final class CashOnDeliveryMetadataResolver
{
    public function __construct(
        private readonly ?ConfigurationRepository $configurationRepository = null
    ) {
    }

    /**
     * @return array{amount: ?string, has_cod: bool, source: ?string, payment_label: ?string}
     */
    public function resolveMetadataByOrderId(int $orderId): array
    {
        $order = $this->loadOrder($orderId);

        return $this->resolveMetadata($order);
    }

    /**
     * @return array{amount: ?string, has_cod: bool, source: ?string, payment_label: ?string}
     */
    public function resolveMetadata(?Order $order): array
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return $this->buildEmptyMetadata();
        }

        return $this->buildCashOnDeliveryMetadata($order);
    }

    public function resolvePaymentAmountByOrderId(int $orderId): ?string
    {
        $order = $this->loadOrder($orderId);

        return $this->resolvePaymentAmount($order);
    }

    public function resolvePaymentAmount(?Order $order): ?string
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return null;
        }

        return $this->resolveCashOnDeliveryPaymentTotal($order);
    }

    /**
     * @return array{amount: null, has_cod: false, source: null, payment_label: null}
     */
    public function buildEmptyMetadata(): array
    {
        return [
            'amount' => null,
            'has_cod' => false,
            'source' => null,
            'payment_label' => null,
        ];
    }

    /**
     * @return array{amount: ?string, has_cod: bool, source: ?string, payment_label: ?string}
     */
    private function buildCashOnDeliveryMetadata(Order $order): array
    {
        $codPaymentsAmount = $this->resolveCashOnDeliveryPaymentTotal($order);

        if (null !== $codPaymentsAmount) {
            return [
                'amount' => $codPaymentsAmount,
                'has_cod' => true,
                'source' => 'payments',
                'payment_label' => $this->resolveOrderPaymentLabel($order),
            ];
        }

        $codAmount = $this->resolveOrderTotalAmount($order);

        return [
            'amount' => $codAmount,
            'has_cod' => false,
            'source' => null === $codAmount ? null : 'order_total',
            'payment_label' => $this->resolveOrderPaymentLabel($order),
        ];
    }

    private function resolveOrderPaymentLabel(Order $order): ?string
    {
        $label = null;

        if (property_exists($order, 'payment')) {
            $label = (string) ($order->payment ?? '');
        }

        $label = trim((string) $label);

        return '' === $label ? null : $label;
    }

    private function resolveOrderTotalAmount(Order $order): ?string
    {
        $candidates = [
            (float) $order->total_paid_tax_incl,
            (float) $order->total_paid,
            (float) $order->total_products_wt,
        ];

        foreach ($candidates as $amount) {
            if ($amount > 0) {
                return $this->formatNumericValue($amount, 2);
            }
        }

        return null;
    }

    private function resolveCashOnDeliveryPaymentTotal(Order $order): ?string
    {
        $matchers = $this->resolveCashOnDeliveryPaymentMatchers();

        if ([] === $matchers) {
            return null;
        }

        try {
            $payments = $order->getOrderPayments();
        } catch (\Throwable) {
            $payments = [];
        }

        if (!is_array($payments) || [] === $payments) {
            return null;
        }

        $total = 0.0;

        foreach ($payments as $payment) {
            $method = '';
            $amount = 0.0;

            if (is_object($payment)) {
                $methodValue = $payment->payment_method ?? ($payment->paymentMethod ?? '');
                $amountValue = $payment->amount ?? 0;

                if (is_scalar($methodValue)) {
                    $method = (string) $methodValue;
                }

                if (is_scalar($amountValue)) {
                    $amount = (float) $amountValue;
                }
            } elseif (is_array($payment)) {
                $method = (string) ($payment['payment_method'] ?? '');
                $amount = (float) ($payment['amount'] ?? 0);
            } else {
                continue;
            }

            if (!$this->isCashOnDeliveryPaymentMethod($method, $matchers)) {
                continue;
            }

            $total += $amount;
        }

        if ($total <= 0) {
            return null;
        }

        return $this->formatNumericValue($total, 2);
    }

    /**
     * @return string[]
     */
    private function resolveCashOnDeliveryPaymentMatchers(): array
    {
        $matchers = [];

        $configuredModule = $this->resolveCashOnDeliveryModule();

        if (null !== $configuredModule && '' !== trim($configuredModule)) {
            $matchers[] = $this->normalizePaymentLabel($configuredModule);

            if (class_exists(Module::class)) {
                try {
                    $moduleInstance = Module::getInstanceByName($configuredModule);
                } catch (\Throwable) {
                    $moduleInstance = null;
                }

                if (null !== $moduleInstance) {
                    if (isset($moduleInstance->displayName)) {
                        $matchers[] = $this->normalizePaymentLabel((string) $moduleInstance->displayName);
                    }

                    if (isset($moduleInstance->name)) {
                        $matchers[] = $this->normalizePaymentLabel((string) $moduleInstance->name);
                    }
                }
            }
        }

        $genericKeywords = ['contra reembolso', 'contrareembolso', 'cash on delivery', 'cashondelivery', 'cod'];

        foreach ($genericKeywords as $keyword) {
            $matchers[] = $this->normalizePaymentLabel($keyword);
        }

        $matchers = array_values(array_unique(array_filter($matchers)));

        return $matchers;
    }

    private function isCashOnDeliveryPaymentMethod(string $label, array $matchers): bool
    {
        if ([] === $matchers) {
            return false;
        }

        $normalized = $this->normalizePaymentLabel($label);

        if ('' === $normalized) {
            return false;
        }

        foreach ($matchers as $matcher) {
            if ('' === $matcher) {
                continue;
            }

            if (str_contains($normalized, $matcher)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePaymentLabel(string $value): string
    {
        $lower = function_exists('mb_strtolower') ? mb_strtolower(trim($value)) : strtolower(trim($value));

        if ('' === $lower) {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]/', '', $lower);

        return (string) ($normalized ?? '');
    }

    private function resolveCashOnDeliveryModule(): ?string
    {
        $repository = $this->getConfigurationRepository();

        if (!$repository instanceof ConfigurationRepository) {
            return null;
        }

        $shopId = Shop::getContextShopID();
        if ($shopId <= 0) {
            $shopId = (int) (Context::getContext()->shop->id ?? 0);
        }

        $configuration = null;
        if ($shopId > 0) {
            $configuration = $repository->findOneByShop($shopId);
        }

        if (!$configuration instanceof SenderConfiguration) {
            $configuration = $repository->findFirst();
        }

        if (!$configuration instanceof SenderConfiguration) {
            return null;
        }

        $moduleName = $configuration->getCashOnDeliveryModule();
        $moduleName = is_string($moduleName) ? trim($moduleName) : null;

        return '' === $moduleName ? null : $moduleName;
    }

    private function loadOrder(int $orderId): ?Order
    {
        if ($orderId <= 0 || !class_exists(Order::class)) {
            return null;
        }

        try {
            $order = new Order($orderId);
        } catch (\Throwable) {
            return null;
        }

        return Validate::isLoadedObject($order) ? $order : null;
    }

    private function formatNumericValue(float $value, int $decimals = 2): string
    {
        $formatted = number_format($value, $decimals, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return '' === $trimmed ? '0' : $trimmed;
    }

    private function getConfigurationRepository(): ?ConfigurationRepository
    {
        return $this->configurationRepository;
    }
}
