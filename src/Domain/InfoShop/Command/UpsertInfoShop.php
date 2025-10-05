<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Command;

@trigger_error(
    'UpsertInfoShop is deprecated. Use UpsertInfoShopCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\UpsertInfoShop', false)) {
    class_alias(UpsertInfoShopCommand::class, __NAMESPACE__ . '\\UpsertInfoShop');
}
