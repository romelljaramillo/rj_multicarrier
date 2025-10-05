<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command;

@trigger_error(
    'DeleteTypeShipment is deprecated. Use DeleteTypeShipmentCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\DeleteTypeShipment', false)) {
    class_alias(DeleteTypeShipmentCommand::class, __NAMESPACE__ . '\\DeleteTypeShipment');
}
