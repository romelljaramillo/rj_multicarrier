<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Command;

@trigger_error(
    'UpsertInfoPackage is deprecated. Use UpsertInfoPackageCommand instead.',
    E_USER_DEPRECATED
);

if (!class_exists(__NAMESPACE__ . '\\UpsertInfoPackage', false)) {
    class_alias(UpsertInfoPackageCommand::class, __NAMESPACE__ . '\\UpsertInfoPackage');
}
