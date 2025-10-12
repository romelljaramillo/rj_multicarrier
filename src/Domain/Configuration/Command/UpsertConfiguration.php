<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Command;

@trigger_error(
    'UpsertConfiguration is deprecated. Use UpsertConfigurationCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\UpsertConfiguration', false)) {
    class_alias(UpsertConfigurationCommand::class, __NAMESPACE__ . '\\UpsertConfiguration');
}
