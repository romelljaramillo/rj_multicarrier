<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\Command;

@trigger_error(
    'UpsertInfoShipment is deprecated. Use UpsertInfoShipmentCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\UpsertInfoShipment', false)) {
    class_alias(UpsertInfoShipmentCommand::class, __NAMESPACE__ . '\\UpsertInfoShipment');
}
