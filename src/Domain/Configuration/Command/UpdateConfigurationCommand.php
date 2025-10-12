<?php
/**
 * DTO for updating Configuration data.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Command;

use Roanja\Module\RjMulticarrier\Domain\Configuration\View\ConfigurationDetailView;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateConfigurationCommand
{
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\GreaterThan(0)]
    public int $id;

    public ?int $id_configuration = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstname = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $lastname = '';

    #[Assert\Length(max: 100)]
    public ?string $company = null;

    #[Assert\Length(max: 100)]
    public ?string $additionalname = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\GreaterThan(0)]
    public int $id_country = 0;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $state = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $city = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $street = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $number = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $postcode = '';

    #[Assert\Length(max: 100)]
    public ?string $additionaladdress = null;

    #[Assert\Type('bool')]
    public ?bool $isbusiness = null;

    #[Assert\Email]
    #[Assert\Length(max: 100)]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $phone = '';

    #[Assert\Length(max: 100)]
    public ?string $vatnumber = null;

    #[Assert\Length(max: 64)]
    public ?string $RJ_ETIQUETA_TRANSP_PREFIX = null;

    #[Assert\Length(max: 255)]
    public ?string $RJ_MODULE_CONTRAREEMBOLSO = null;

    /**
     * @var int[]
     */
    #[Assert\NotBlank(message: 'Selecciona al menos una tienda.')]
    #[Assert\Count(min: 1, minMessage: 'Selecciona al menos una tienda.')]
    public array $shop_association = [];

    private function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * Factory from read model.
     *
     * @param int[] $contextShopIds
     */
    public static function fromConfiguration(ConfigurationDetailView $detail): self
    {
        $command = new self($detail->getId());
        $command->id_configuration = $detail->getId();
        $command->firstname = $detail->getFirstName();
        $command->lastname = $detail->getLastName();
        $command->company = self::nullableString($detail->getCompany());
        $command->additionalname = self::nullableString($detail->getAdditionalName());
        $command->id_country = $detail->getCountryId();
        $command->state = $detail->getState();
        $command->city = $detail->getCity();
        $command->street = $detail->getStreet();
        $command->number = $detail->getStreetNumber();
        $command->postcode = $detail->getPostcode();
        $command->additionaladdress = self::nullableString($detail->getAdditionalAddress());
        $command->isbusiness = $detail->isBusiness();
        $command->email = self::nullableString($detail->getEmail());
        $command->phone = $detail->getPhone();
        $command->vatnumber = self::nullableString($detail->getVatNumber());
        $command->shop_association = self::normalizeShopIds($detail->getShops());
        $command->RJ_ETIQUETA_TRANSP_PREFIX = self::nullableString($detail->getLabelPrefix());
        $command->RJ_MODULE_CONTRAREEMBOLSO = self::nullableString($detail->getCashOnDeliveryModule());

        return $command;
    }

    /**
     * Factory from raw form payload (used after submission).
     *
     * @param array<string, mixed> $data
     * @param int[] $contextShopIds
     */
    public static function fromArray(int $ConfigurationId, array $data, int $fallbackShopId, array $contextShopIds): self
    {
        $command = new self($ConfigurationId);
        $command->id_configuration = $ConfigurationId;
        $command->firstname = (string) ($data['firstname'] ?? '');
        $command->lastname = (string) ($data['lastname'] ?? '');
        $command->company = self::nullableString($data['company'] ?? null);
        $command->additionalname = self::nullableString($data['additionalname'] ?? null);
        $command->id_country = (int) ($data['id_country'] ?? 0);
        $command->state = (string) ($data['state'] ?? '');
        $command->city = (string) ($data['city'] ?? '');
        $command->street = (string) ($data['street'] ?? '');
        $command->number = (string) ($data['number'] ?? '');
        $command->postcode = (string) ($data['postcode'] ?? '');
        $command->additionaladdress = self::nullableString($data['additionaladdress'] ?? null);
        $command->isbusiness = array_key_exists('isbusiness', $data) ? self::toNullableBool($data['isbusiness']) : null;
        $command->email = self::nullableString($data['email'] ?? null);
        $command->phone = (string) ($data['phone'] ?? '');
        $command->vatnumber = self::nullableString($data['vatnumber'] ?? null);
        $command->shop_association = self::normalizeShopIds($data['shop_association'] ?? null);
        $command->RJ_ETIQUETA_TRANSP_PREFIX = self::nullableString($data['RJ_ETIQUETA_TRANSP_PREFIX'] ?? null);
        $command->RJ_MODULE_CONTRAREEMBOLSO = self::nullableString($data['RJ_MODULE_CONTRAREEMBOLSO'] ?? null);
        $command->ensureShopAssociation($fallbackShopId, $contextShopIds);

        return $command;
    }

    /**
     * Ensures at least one shop association.
     *
     * @param int[] $contextShopIds
     */
    public function ensureShopAssociation(int $fallbackShopId, array $contextShopIds): void
    {
        if (!empty($this->shop_association)) {
            return;
        }

        if ($fallbackShopId > 0) {
            $this->shop_association = [$fallbackShopId];

            return;
        }

        if (!empty($contextShopIds)) {
            $this->shop_association = self::normalizeShopIds($contextShopIds);
        }
    }

    /**
     * @return int[]
     */
    public function getNormalizedShopAssociation(): array
    {
        return self::normalizeShopIds($this->shop_association);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private static function toNullableBool(mixed $value): ?bool
    {
        if (null === $value) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ('' === $normalized) {
                return null;
            }

            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @param mixed $value
     *
     * @return int[]
     */
    private static function normalizeShopIds(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = array_map('intval', $value);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }
}
