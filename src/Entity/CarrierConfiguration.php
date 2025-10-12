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
}
