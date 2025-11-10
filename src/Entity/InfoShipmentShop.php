<?php
/**
 * Mapping entity between info shipments and shop ids (legacy table).
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_info_shipment_shop')]
class InfoShipmentShop
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: InfoShipment::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_info_shipment', referencedColumnName: 'id_info_shipment', nullable: false, onDelete: 'CASCADE')]
    private InfoShipment $infoShipment;

    #[ORM\Id]
    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $shopId;

    public function __construct(InfoShipment $infoShipment, int $shopId)
    {
        $this->infoShipment = $infoShipment;
        $this->shopId = $shopId;
    }

    public function getInfoShipment(): InfoShipment
    {
        return $this->infoShipment;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }
}
