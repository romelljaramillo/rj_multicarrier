<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use PrestaShop\PrestaShop\Adapter\Configuration as LegacyConfiguration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\SaveExtraConfigurationCommand;

final class SaveExtraConfigurationHandler
{
    public function __construct(private readonly LegacyConfiguration $configuration)
    {
    }

    public function handle(SaveExtraConfigurationCommand $command): void
    {
        $shopId = $this->resolveShopId();

        if (0 === $shopId) {
            throw new \RuntimeException('No se pudo determinar la tienda en contexto.');
        }

        $constraint = ShopConstraint::shop($shopId);

        $this->configuration->set('RJ_ETIQUETA_TRANSP_PREFIX', $command->getLabelPrefix(), $constraint);
        $this->configuration->set('RJ_MODULE_CONTRAREEMBOLSO', $command->getCashOnDeliveryModule(), $constraint);
    }

    private function resolveShopId(): int
    {
        if (class_exists('\Context')) {
            $context = call_user_func(['\Context', 'getContext']);
            if (isset($context->shop->id)) {
                return (int) $context->shop->id;
            }
        }

        return 0;
    }
}
