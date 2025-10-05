<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command;

@trigger_error(
    'UpsertTypeShipment is deprecated. Use UpsertTypeShipmentCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\UpsertTypeShipment', false)) {
    class_alias(UpsertTypeShipmentCommand::class, __NAMESPACE__ . '\\UpsertTypeShipment');
}
