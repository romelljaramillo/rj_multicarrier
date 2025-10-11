<?php
/**
 * Mapping entity between shipments and shop ids (legacy table).
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_shipment_shop')]
class ShipmentShop
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Shipment::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_shipment', referencedColumnName: 'id_shipment', nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Id]
    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $shopId;

    public function __construct(Shipment $shipment, int $shopId)
    {
        $this->shipment = $shipment;
        $this->shopId = $shopId;
    }

    public function getShipment(): Shipment
    {
        return $this->shipment;
    }

    public function setShipment(Shipment $shipment): void
    {
        $this->shipment = $shipment;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }
}
