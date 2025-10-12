<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: self::TABLE_NAME)]
class CarrierShop
{
    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_carrier_shop';

    /**
     * Composite primary key: carrier + shop
     */
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Carrier::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_carrier', referencedColumnName: 'id_carrier', nullable: false, onDelete: 'CASCADE')]
    private Carrier $carrier;

    #[ORM\Id]
    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $idShop;

    public function __construct(Carrier $carrier, int $idShop)
    {
        $this->carrier = $carrier;
        $this->idShop = $idShop;
    }

    public function getCarrier(): Carrier
    {
        return $this->carrier;
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
