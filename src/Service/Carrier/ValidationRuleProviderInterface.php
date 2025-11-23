<?php
/**
 * Contract for retrieving validation rules used by the carrier applier.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

interface ValidationRuleProviderInterface
{
    /**
     * @return array<int, array{priority:int,name:string,conditions:array<string, mixed>,actions:array<string, array<int>>}>
     */
    public function getActiveRules(?int $shopId): array;
}
