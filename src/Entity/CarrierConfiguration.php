<?php
/**
 * Doctrine entity for storing carrier configuration key/value pairs.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository::class)]
#[ORM\Table(name: CarrierConfiguration::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'rj_multicarrier_carrier_conf_unique', columns: ['id_carrier', 'id_type_shipment', 'id_shop_group', 'id_shop', 'name'])]
class CarrierConfiguration
{
    use TimestampableTrait;

    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_carrier_configuration';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_carrier_configuration', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Carrier::class)]
    #[ORM\JoinColumn(name: 'id_carrier', referencedColumnName: 'id_carrier', nullable: true, onDelete: 'CASCADE')]
    private ?Carrier $carrier = null;

    #[ORM\ManyToOne(targetEntity: TypeShipment::class)]
    #[ORM\JoinColumn(name: 'id_type_shipment', referencedColumnName: 'id_type_shipment', nullable: true, onDelete: 'CASCADE')]
    private ?TypeShipment $typeShipment = null;

    #[ORM\Column(name: 'id_shop_group', type: 'integer', nullable: true)]
    private ?int $shopGroupId = null;

    #[ORM\Column(name: 'id_shop', type: 'integer', nullable: true)]
    private ?int $shopId = null;

    #[ORM\Column(name: 'name', type: 'string', length: 254)]
    private string $name;

    #[ORM\Column(name: 'value', type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column(name: 'is_required', type: 'boolean', options: ['default' => false])]
    private bool $isRequired = false;

    #[ORM\Column(name: 'field_group', type: 'string', length: 64, nullable: true)]
    private ?string $fieldGroup = null;

    #[ORM\Column(name: 'field_type', type: 'string', length: 32, nullable: true, options: ['default' => 'text'])]
    private ?string $fieldType = 'text';

    #[ORM\Column(name: 'payload_direction', type: 'string', length: 16, nullable: true)]
    private ?string $payloadDirection = null;

    #[ORM\Column(name: 'payload_key', type: 'string', length: 191, nullable: true)]
    private ?string $payloadKey = null;

    #[ORM\Column(name: 'payload_source', type: 'string', length: 191, nullable: true)]
    private ?string $payloadSource = null;

    #[ORM\Column(name: 'position', type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'is_dynamic', type: 'boolean', options: ['default' => false])]
    private bool $isDynamic = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function setCarrier(?Carrier $carrier): self
    {
        $this->carrier = $carrier;

        return $this;
    }

    public function getTypeShipment(): ?TypeShipment
    {
        return $this->typeShipment;
    }

    public function setTypeShipment(?TypeShipment $typeShipment): self
    {
        $this->typeShipment = $typeShipment;

        return $this;
    }

    public function getShopGroupId(): ?int
    {
        return $this->shopGroupId;
    }

    public function setShopGroupId(?int $shopGroupId): self
    {
        $this->shopGroupId = $shopGroupId;

        return $this;
    }

    public function getShopId(): ?int
    {
        return $this->shopId;
    }

    public function setShopId(?int $shopId): self
    {
        $this->shopId = $shopId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): self
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    public function getFieldGroup(): ?string
    {
        return $this->fieldGroup;
    }

    public function setFieldGroup(?string $fieldGroup): self
    {
        $this->fieldGroup = $fieldGroup ? substr($fieldGroup, 0, 64) : null;

        return $this;
    }

    public function getFieldType(): string
    {
        return $this->fieldType ?? 'text';
    }

    public function setFieldType(?string $fieldType): self
    {
        $this->fieldType = $fieldType ? substr($fieldType, 0, 32) : 'text';

        return $this;
    }

    public function getPayloadDirection(): ?string
    {
        return $this->payloadDirection;
    }

    public function setPayloadDirection(?string $payloadDirection): self
    {
        $this->payloadDirection = $payloadDirection ? substr($payloadDirection, 0, 16) : null;

        return $this;
    }

    public function getPayloadKey(): ?string
    {
        return $this->payloadKey;
    }

    public function setPayloadKey(?string $payloadKey): self
    {
        $this->payloadKey = $payloadKey ? substr($payloadKey, 0, 191) : null;

        return $this;
    }

    public function getPayloadSource(): ?string
    {
        return $this->payloadSource;
    }

    public function setPayloadSource(?string $payloadSource): self
    {
        $this->payloadSource = $payloadSource ? substr($payloadSource, 0, 191) : null;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isDynamic(): bool
    {
        return $this->isDynamic;
    }

    public function setIsDynamic(bool $isDynamic): self
    {
        $this->isDynamic = $isDynamic;

        return $this;
    }
}
