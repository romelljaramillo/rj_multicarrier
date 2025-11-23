<?php
/**
 * Small helper to resolve employee display names from their identifiers without persisting them.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Util;

use \Employee;
use \Validate;

final class EmployeeFormatter
{
    /**
     * @var array<int, string|null>
     */
    private static array $nameCache = [];

    public static function resolveName(?int $employeeId): ?string
    {
        if (null === $employeeId || $employeeId <= 0) {
            return null;
        }

        if (array_key_exists($employeeId, self::$nameCache)) {
            return self::$nameCache[$employeeId];
        }

        if (!class_exists(Employee::class)) {
            return self::$nameCache[$employeeId] = null;
        }

        try {
            $employee = new Employee($employeeId);
            if (!Validate::isLoadedObject($employee)) {
                return self::$nameCache[$employeeId] = null;
            }

            $fullName = trim(sprintf('%s %s', (string) $employee->firstname, (string) $employee->lastname));
            if ('' === $fullName) {
                $fullName = sprintf('#%d', $employeeId);
            }

            self::$nameCache[$employeeId] = $fullName;

            return $fullName;
        } catch (\Throwable) {
            self::$nameCache[$employeeId] = null;

            return null;
        }
    }
}
