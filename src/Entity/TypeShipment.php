<?php
/**
 * Doctrine entity for carrier shipment types.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository::class)]
#[ORM\Table(name: self::TABLE_NAME)]
class TypeShipment
{
    use TimestampableTrait;

    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_type_shipment';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_type_shipment', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'id_carrier_company', referencedColumnName: 'id_carrier_company', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\Column(name: 'name', type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(name: 'id_bc', type: 'string', length: 100)]
    private string $businessCode;

    #[ORM\Column(name: 'id_reference_carrier', type: 'integer', nullable: true)]
    private ?int $referenceCarrierId = null;

    #[ORM\Column(name: 'active', type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'settings', type: 'json', nullable: true)]
    private ?array $settings = null;

    public function __construct(Company $company, string $name, string $businessCode)
    {
        $this->company = $company;
        $this->name = $name;
        $this->businessCode = $businessCode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;

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

    public function getBusinessCode(): string
    {
        return $this->businessCode;
    }

    public function setBusinessCode(string $businessCode): self
    {
        $this->businessCode = $businessCode;

        return $this;
    }

    public function getReferenceCarrierId(): ?int
    {
        return $this->referenceCarrierId;
    }

    public function setReferenceCarrierId(?int $referenceCarrierId): self
    {
        $this->referenceCarrierId = $referenceCarrierId;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function setSettings(?array $settings): self
    {
        $this->settings = $settings ?? [];

        return $this;
    }

    public function mergeSettings(array $settings): self
    {
        $current = $this->getSettings();
        $this->settings = array_merge($current, $settings);

        return $this;
    }
}
