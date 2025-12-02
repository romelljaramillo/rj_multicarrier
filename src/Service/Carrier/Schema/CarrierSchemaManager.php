<?php
/**
 * Helper responsible for validating and normalizing per-carrier configuration schemas.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier\Schema;

use InvalidArgumentException;
use Roanja\Module\RjMulticarrier\Entity\Carrier;

final class CarrierSchemaManager
{
    private const DEFAULT_TAB_CODE = 'general';
    private const DEFAULT_TAB_LABEL = 'General';

    /**
     * Decode a JSON string into an array-based schema. Returns an empty array if the input is empty.
     */
    public function decodeString(?string $raw): array
    {
        if (null === $raw) {
            return [];
        }

        $raw = trim($raw);
        if ('' === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidArgumentException('JSON inválido: ' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('El esquema debe representarse como un objeto/array JSON.');
        }

        return $decoded;
    }

    /**
     * Provide a human friendly JSON string for persisting back into the form.
     */
    public function encodePretty(?array $schema): string
    {
        if (empty($schema)) {
            return '';
        }

        $encoded = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return false === $encoded ? '' : (string) $encoded;
    }

    /**
     * Validate that the schema has at least one field and conforms to the expected structure.
     */
    public function assertValid(array $schema): void
    {
        if (empty($schema)) {
            return;
        }

        $this->extractFieldDefinitionsFromArray($schema);
    }

    /**
     * Shortcut to gather field definitions for a persisted carrier entity.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractFieldDefinitionsFromCarrier(Carrier $carrier): array
    {
        $schema = $carrier->getConfigurationSchema();
        if (empty($schema)) {
            return [];
        }

        return $this->extractFieldDefinitionsFromArray($schema);
    }

    /**
     * Flatten the tabs/groups/fields structure into a list of field definitions consumable by the UI/seeders.
     *
     * @return array<int, array{
     *     name: string,
     *     label: string,
     *     description?: ?string,
     *     required?: bool,
     *     value?: mixed,
     *     field_group?: ?string,
     *     field_group_label?: ?string,
     *     tab?: string,
     *     tab_label?: string,
     *     field_type?: string,
     *     payload_direction?: ?string,
     *     payload_key?: ?string,
     *     payload_source?: ?string,
     *     position?: int
     * }>
     */
    public function extractFieldDefinitionsFromArray(array $schema): array
    {
        if (empty($schema)) {
            return [];
        }

        $definitions = [];
        $tabs = [];

        if (isset($schema['tabs'])) {
            if (!is_array($schema['tabs'])) {
                throw new InvalidArgumentException('La clave "tabs" debe contener un array.');
            }

            $tabs = $schema['tabs'];
        } elseif (isset($schema['fields'])) {
            if (!is_array($schema['fields'])) {
                throw new InvalidArgumentException('La clave "fields" debe ser un array.');
            }

            $tabs = [[
                'code' => self::DEFAULT_TAB_CODE,
                'label' => self::DEFAULT_TAB_LABEL,
                'fields' => $schema['fields'],
            ]];
        } else {
            throw new InvalidArgumentException('El esquema debe incluir "tabs" o "fields".');
        }

        if (empty($tabs)) {
            throw new InvalidArgumentException('Debe definirse al menos un tab en el esquema.');
        }

        $seenNames = [];

        foreach ($tabs as $tabIndex => $tab) {
            if (!is_array($tab)) {
                throw new InvalidArgumentException(sprintf('El tab #%d no es un objeto válido.', $tabIndex + 1));
            }

            $tabCode = $this->resolveCode($tab, sprintf('tabs[%d]', $tabIndex), self::DEFAULT_TAB_CODE . '_' . ($tabIndex + 1));
            $tabLabel = $this->resolveLabel($tab, $tabCode, self::DEFAULT_TAB_LABEL);
            $tabBasePosition = (int) (($tabIndex + 1) * 1000);

            if (isset($tab['fields'])) {
                if (!is_array($tab['fields'])) {
                    throw new InvalidArgumentException(sprintf('La clave "fields" del tab "%s" debe ser un array.', $tabCode));
                }

                $definitions = array_merge(
                    $definitions,
                    $this->collectFieldDefinitions($tab['fields'], $tabCode, $tabLabel, null, null, $tabBasePosition)
                );
            }

            if (isset($tab['groups'])) {
                if (!is_array($tab['groups'])) {
                    throw new InvalidArgumentException(sprintf('La clave "groups" del tab "%s" debe ser un array.', $tabCode));
                }

                foreach ($tab['groups'] as $groupIndex => $group) {
                    if (!is_array($group)) {
                        throw new InvalidArgumentException(sprintf('El grupo #%d del tab "%s" no es válido.', $groupIndex + 1, $tabCode));
                    }

                    $groupCode = $this->resolveCode($group, sprintf('%s.groups[%d]', $tabCode, $groupIndex), $tabCode . '_group_' . ($groupIndex + 1));
                    $groupLabel = $this->resolveLabel($group, $groupCode, ucfirst(str_replace('_', ' ', $groupCode)));
                    $groupFields = $group['fields'] ?? [];
                    if (!is_array($groupFields)) {
                        throw new InvalidArgumentException(sprintf('El grupo "%s" dentro del tab "%s" debe incluir un array "fields".', $groupCode, $tabCode));
                    }

                    $definitions = array_merge(
                        $definitions,
                        $this->collectFieldDefinitions(
                            $groupFields,
                            $tabCode,
                            $tabLabel,
                            $groupCode,
                            $groupLabel,
                            $tabBasePosition + (($groupIndex + 1) * 100)
                        )
                    );
                }
            }
        }

        if (empty($definitions)) {
            throw new InvalidArgumentException('El esquema no contiene campos configurables.');
        }

        foreach ($definitions as $definition) {
            $key = strtolower($definition['name']);
            if (isset($seenNames[$key])) {
                throw new InvalidArgumentException(sprintf('El campo "%s" está duplicado en el esquema.', $definition['name']));
            }
            $seenNames[$key] = true;
        }

        usort($definitions, static function (array $left, array $right): int {
            return ($left['position'] ?? 0) <=> ($right['position'] ?? 0);
        });

        return $definitions;
    }

    /**
     * @param array<int, mixed> $fields
     * @return array<int, array<string, mixed>>
     */
    private function collectFieldDefinitions(
        array $fields,
        string $tabCode,
        string $tabLabel,
        ?string $groupCode,
        ?string $groupLabel,
        int $basePosition
    ): array {
        $definitions = [];

        foreach ($fields as $fieldIndex => $field) {
            if (!is_array($field)) {
                throw new InvalidArgumentException(sprintf('El campo #%d del tab "%s" no es un objeto válido.', $fieldIndex + 1, $tabCode));
            }

            $name = trim((string) ($field['name'] ?? ''));
            if ('' === $name) {
                throw new InvalidArgumentException(sprintf('Hay un campo sin nombre en el tab "%s".', $tabCode));
            }

            $label = trim((string) ($field['label'] ?? ''));
            if ('' === $label) {
                $label = $name;
            }

            $description = null;
            if (array_key_exists('description', $field)) {
                $descriptionCandidate = trim((string) $field['description']);
                $description = '' === $descriptionCandidate ? null : $descriptionCandidate;
            }

            $required = isset($field['required']) ? (bool) $field['required'] : true;
            $value = $field['value'] ?? ($field['default'] ?? null);
            $type = trim((string) ($field['type'] ?? 'text'));
            $payload = isset($field['payload']) && is_array($field['payload']) ? $field['payload'] : [];
            $position = isset($field['position']) ? (int) $field['position'] : ($basePosition + ($fieldIndex + 1));

            $definitions[] = [
                'name' => $name,
                'label' => $label,
                'description' => $description,
                'required' => $required,
                'value' => $value,
                'field_group' => $this->buildGroupKey($tabCode, $groupCode),
                'field_group_label' => $groupLabel ?? $tabLabel,
                'tab' => $tabCode,
                'tab_label' => $tabLabel,
                'field_type' => '' === $type ? 'text' : $type,
                'payload_direction' => isset($payload['direction']) ? (string) $payload['direction'] : null,
                'payload_key' => isset($payload['key']) ? (string) $payload['key'] : null,
                'payload_source' => isset($payload['source']) ? (string) $payload['source'] : null,
                'position' => $position,
            ];
        }

        return $definitions;
    }

    private function resolveCode(array $node, string $path, string $fallback): string
    {
        $code = isset($node['code']) ? trim((string) $node['code']) : '';
        if ('' !== $code) {
            return $this->slugify($code) ?: $fallback;
        }

        if (isset($node['label'])) {
            $labelSlug = $this->slugify((string) $node['label']);
            if ('' !== $labelSlug) {
                return $labelSlug;
            }
        }

        throw new InvalidArgumentException(sprintf('El nodo "%s" requiere una clave "code" o "label" válida.', $path));
    }

    private function resolveLabel(array $node, string $code, string $fallback): string
    {
        $label = isset($node['label']) ? trim((string) $node['label']) : '';

        if ('' !== $label) {
            return $label;
        }

        return $fallback ?: ucfirst(str_replace('_', ' ', $code));
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function buildGroupKey(string $tabCode, ?string $groupCode): string
    {
        $key = $tabCode;
        if (null !== $groupCode && '' !== $groupCode) {
            $key .= '.' . $groupCode;
        }

        return substr($key, 0, 64);
    }
}
