<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: self::TABLE_NAME)]
class CompanyShop
{
    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_company_shop';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_company_shop', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_carrier_company', referencedColumnName: 'id_carrier_company', onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $idShop;

    public function __construct(Company $company, int $idShop)
    {
        $this->company = $company;
        $this->idShop = $idShop;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getIdShop(): int
    {
        return $this->idShop;
    }

    public function setIdShop(int $idShop): self
    {
        $this->idShop = $idShop;

        return $this;
    }
}
