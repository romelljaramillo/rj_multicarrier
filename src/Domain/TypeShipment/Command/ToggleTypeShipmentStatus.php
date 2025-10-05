<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command;

@trigger_error(
    'ToggleTypeShipmentStatus is deprecated. Use ToggleTypeShipmentStatusCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\ToggleTypeShipmentStatus', false)) {
    class_alias(ToggleTypeShipmentStatusCommand::class, __NAMESPACE__ . '\\ToggleTypeShipmentStatus');
}
