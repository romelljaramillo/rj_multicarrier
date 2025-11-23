<?php
/**
 * Grid data decorator to enrich info shipment rows with CSRF tokens.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoShipment;

use Context;
use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Roanja\Module\RjMulticarrier\Util\EmployeeFormatter;

final class InfoShipmentGridDataFactoryDecorator implements GridDataFactoryInterface
{
    private const FORM_FIELD_MAP = [
        'id_info_shipment' => 'id_info_shipment_raw',
        'id_reference_carrier' => 'id_reference_carrier',
        'id_type_shipment' => 'id_type_shipment',
        'carrier_id' => 'id_carrier',
        'rj_quantity' => 'quantity',
        'rj_weight' => 'weight',
        'rj_length' => 'length',
        'rj_width' => 'width',
        'rj_height' => 'height',
        'rj_cash_ondelivery' => 'cash_ondelivery',
        'rj_message' => 'message',
        'rj_hour_from' => 'hour_from',
        'rj_hour_until' => 'hour_until',
        'rj_retorno' => 'retorno',
        'rj_rcs' => 'rcs',
        'rj_vsec' => 'vsec',
        'rj_dorig' => 'dorig',
        'rj_employee_id' => 'id_employee',
    ];

    public function __construct(
        private readonly GridDataFactoryInterface $infoShipmentDoctrineGridDataFactory
    ) {
    }

    public function getData(SearchCriteriaInterface $searchCriteria)
    {
        $gridData = $this->infoShipmentDoctrineGridDataFactory->getData($searchCriteria);

        $normalizedRecords = [];

        foreach ($gridData->getRecords()->all() as $record) {
            $rawId = (int) ($record['id_info_shipment'] ?? 0);

            $record['id_info_shipment_raw'] = $rawId;
            $record['id_info_shipment'] = $rawId > 0 ? (string) $rawId : '-';

            if (!isset($record['employee_name'])) {
                $record['employee_name'] = EmployeeFormatter::resolveName(isset($record['id_employee']) ? (int) $record['id_employee'] : null);
            }

            $inlineValues = $this->buildInlineValues($record);
            $record['inline_form'] = $inlineValues;
            $record['form_prefill'] = $this->encodeInlineValues($inlineValues);

            $normalizedRecords[] = $record;
        }

        return new GridData(
            new RecordCollection($normalizedRecords),
            $gridData->getRecordsTotal(),
            $gridData->getQuery()
        );
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, scalar|null>
     */
    private function buildInlineValues(array $record): array
    {
        $payload = [];

        foreach (self::FORM_FIELD_MAP as $formField => $recordKey) {
            if (!array_key_exists($recordKey, $record)) {
                continue;
            }

            $value = $record[$recordKey];

            if (null === $value) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($formField === 'rj_rcs') {
                $value = (int) ((bool) $value);
            }

            $payload[$formField] = $value;
        }

        $defaultEmployee = $this->getDefaultEmployee();

        if ((!isset($payload['rj_employee_id']) || (int) $payload['rj_employee_id'] <= 0) && null !== $defaultEmployee['id']) {
            $payload['rj_employee_id'] = $defaultEmployee['id'];
        }

        return $payload;
    }

    private function encodeInlineValues(array $values): string
    {
        if ([] === $values) {
            return '';
        }

        $encoded = json_encode($values);

        if (false === $encoded || '' === $encoded) {
            return '';
        }

        return rtrim(strtr(base64_encode($encoded), '+/', '-_'), '=');
    }

    /**
     * @return array{id: int|null, name: string|null}
     */
    private function getDefaultEmployee(): array
    {
        static $cache = null;

        if (null !== $cache) {
            return $cache;
        }

        $context = Context::getContext();
        $employee = $context->employee ?? null;

        if (null === $employee) {
            $cache = ['id' => null, 'name' => null];

            return $cache;
        }

        $id = isset($employee->id) ? (int) $employee->id : null;
        $fullName = trim(sprintf('%s %s', (string) ($employee->firstname ?? ''), (string) ($employee->lastname ?? '')));

        if ('' === $fullName && isset($employee->email)) {
            $fullName = (string) $employee->email;
        }

        if ('' === $fullName && isset($employee->id)) {
            $fullName = sprintf('#%d', (int) $employee->id);
        }

        $cache = [
            'id' => $id,
            'name' => '' !== $fullName ? $fullName : null,
        ];

        return $cache;
    }
}
