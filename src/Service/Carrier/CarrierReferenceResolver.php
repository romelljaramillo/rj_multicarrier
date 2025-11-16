<?php
/**
 * Utility to translate between PrestaShop carrier ids and their stable references.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

final class CarrierReferenceResolver
{
    /**
     * @var array<int, int> map of carrier id => reference id
     */
    private array $carrierToReference = [];

    /**
     * @var array<int, int> map of reference id => preferred carrier id
     */
    private array $referenceToCarrier = [];

    /**
     * @var array<int, int> map of reference id => deleted flag of the preferred carrier
     */
    private array $referenceDeletion = [];

    /**
     * @var array<int, bool> register of references that exist in the carrier table
     */
    private array $knownReferences = [];

    /**
     * Converts a list of carrier identifiers to their corresponding references, preserving order.
     * Additionally returns the original carrier assigned to each reference for fallback purposes.
     *
     * @param int[] $carrierIds
     *
     * @return array{references: int[], map: array<int, int>}
     */
    public function mapCarrierIdsToReferences(array $carrierIds): array
    {
        $carrierIds = $this->normalizeIntArray($carrierIds);

        if (empty($carrierIds)) {
            return [
                'references' => [],
                'map' => [],
            ];
        }

        $this->preloadByCarrierIds($carrierIds);

        $references = [];
        $referenceToCarrier = [];

        foreach ($carrierIds as $carrierId) {
            $referenceId = $this->carrierToReference[$carrierId] ?? $carrierId;

            if ($referenceId > 0) {
                $references[] = $referenceId;

                if (!isset($referenceToCarrier[$referenceId])) {
                    $referenceToCarrier[$referenceId] = $carrierId;
                }
            }
        }

        return [
            'references' => array_values(array_unique($references)),
            'map' => $referenceToCarrier,
        ];
    }

    /**
     * Ensures the provided identifiers are converted to carrier references.
     * Inputs can be current carrier ids or already stable references.
     *
     * @param int[] $identifiers
     *
     * @return int[]
     */
    public function ensureReferences(array $identifiers): array
    {
        $identifiers = $this->normalizeIntArray($identifiers);

        if (empty($identifiers)) {
            return [];
        }

        $this->preloadByCarrierIds($identifiers);
        $this->preloadByReferenceIds($identifiers);

        $references = [];

        foreach ($identifiers as $identifier) {
            $referenceId = $this->carrierToReference[$identifier] ?? $identifier;

            if ($referenceId > 0) {
                $references[] = $referenceId;
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * Resolves carrier ids for the given references, preferring active carriers when available.
     *
     * @param int[] $references
     *
     * @return array<int, int> map of reference id => carrier id
     */
    public function mapReferencesToCarrierIds(array $references): array
    {
        $references = $this->normalizeIntArray($references);

        if (empty($references)) {
            return [];
        }

        $this->preloadByReferenceIds($references);

        $result = [];

        foreach ($references as $referenceId) {
            if (isset($this->referenceToCarrier[$referenceId])) {
                $result[$referenceId] = $this->referenceToCarrier[$referenceId];
            }
        }

        return $result;
    }

    /**
     * @param int[] $values
     *
     * @return int[]
     */
    private function normalizeIntArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $intValue = (int) $value;

            if ($intValue <= 0) {
                continue;
            }

            $normalized[] = $intValue;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param int[] $carrierIds
     */
    private function preloadByCarrierIds(array $carrierIds): void
    {
        if (empty($carrierIds) || !class_exists('\\Db')) {
            return;
        }

        $pending = array_diff($carrierIds, array_keys($this->carrierToReference));

        if (empty($pending)) {
            return;
        }

        $list = implode(',', array_map('intval', $pending));

        if ('' === $list) {
            return;
        }

        $sql = sprintf(
            'SELECT id_carrier, id_reference, deleted FROM `%scarrier` WHERE id_carrier IN (%s)',
            _DB_PREFIX_,
            $list
        );

        $rows = \Db::getInstance()->executeS($sql) ?: [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $carrierId = (int) ($row['id_carrier'] ?? 0);
            $referenceId = (int) ($row['id_reference'] ?? 0);
            $deleted = (int) ($row['deleted'] ?? 0);

            if ($carrierId <= 0 || $referenceId <= 0) {
                continue;
            }

            $this->registerCarrierMapping($carrierId, $referenceId, $deleted);
        }
    }

    /**
     * @param int[] $referenceIds
     */
    private function preloadByReferenceIds(array $referenceIds): void
    {
        if (empty($referenceIds) || !class_exists('\\Db')) {
            return;
        }

        $needsRefresh = [];

        foreach ($referenceIds as $referenceId) {
            $known = isset($this->referenceToCarrier[$referenceId]);
            $isActive = ($this->referenceDeletion[$referenceId] ?? 1) === 0;

            if (!$known || !$isActive) {
                $needsRefresh[$referenceId] = true;
            }
        }

        if (empty($needsRefresh)) {
            return;
        }

        $list = implode(',', array_map('intval', array_keys($needsRefresh)));

        if ('' === $list) {
            return;
        }

        $sql = sprintf(
            'SELECT id_reference, id_carrier, deleted FROM `%scarrier`'
            . ' WHERE id_reference IN (%s)'
            . ' ORDER BY deleted ASC, id_carrier DESC',
            _DB_PREFIX_,
            $list
        );

        $rows = \Db::getInstance()->executeS($sql) ?: [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $referenceId = (int) ($row['id_reference'] ?? 0);
            $carrierId = (int) ($row['id_carrier'] ?? 0);
            $deleted = (int) ($row['deleted'] ?? 0);

            if ($referenceId <= 0 || $carrierId <= 0) {
                continue;
            }

            $this->registerCarrierMapping($carrierId, $referenceId, $deleted);
        }
    }

    private function registerCarrierMapping(int $carrierId, int $referenceId, int $deleted): void
    {
        if (!isset($this->carrierToReference[$carrierId])) {
            $this->carrierToReference[$carrierId] = $referenceId;
        }

        $this->knownReferences[$referenceId] = true;

        $currentCarrierId = $this->referenceToCarrier[$referenceId] ?? null;
        $currentDeleted = $this->referenceDeletion[$referenceId] ?? null;

        if (null === $currentCarrierId) {
            $this->referenceToCarrier[$referenceId] = $carrierId;
            $this->referenceDeletion[$referenceId] = $deleted;

            return;
        }

        if (($currentDeleted ?? 1) !== 0 && $deleted === 0) {
            $this->referenceToCarrier[$referenceId] = $carrierId;
            $this->referenceDeletion[$referenceId] = $deleted;

            return;
        }

        if ($deleted === ($currentDeleted ?? 0) && $carrierId > $currentCarrierId) {
            $this->referenceToCarrier[$referenceId] = $carrierId;
            $this->referenceDeletion[$referenceId] = $deleted;
        }
    }
}
