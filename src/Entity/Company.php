<?php
/**
 * Doctrine entity representing carrier companies.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\CompanyRepository::class)]
#[ORM\Table(name: self::TABLE_NAME)]
class Company
{
    use TimestampableTrait;

    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_company';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_carrier_company', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 50)]
    private string $name;

    #[ORM\Column(name: 'shortname', type: 'string', length: 4)]
    private string $shortName;

    #[ORM\Column(name: 'icon', type: 'string', length: 250, nullable: true)]
    private ?string $icon = null;

    public function __construct(string $name, string $shortName)
    {
        $this->name = $name;
        $this->shortName = $shortName;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): self
    {
        $this->shortName = $shortName;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }
}
