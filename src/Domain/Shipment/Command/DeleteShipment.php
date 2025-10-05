<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Command;

@trigger_error(
    'DeleteShipment is deprecated. Use DeleteShipmentCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\DeleteShipment', false)) {
    class_alias(DeleteShipmentCommand::class, __NAMESPACE__ . '\\DeleteShipment');
}
