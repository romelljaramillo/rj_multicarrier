<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Command;

@trigger_error(
    'CreateShipment is deprecated. Use CreateShipmentCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\CreateShipment', false)) {
    class_alias(CreateShipmentCommand::class, __NAMESPACE__ . '\\CreateShipment');
}
