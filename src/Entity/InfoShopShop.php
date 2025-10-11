<?php
/**
 * Mapping entity between infoshop and shop ids.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_infoshop_shop')]
class InfoShopShop
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: InfoShop::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_infoshop', referencedColumnName: 'id_infoshop', nullable: false, onDelete: 'CASCADE')]
    private InfoShop $infoShop;

    #[ORM\Id]
    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $shopId;

    public function __construct(InfoShop $infoShop, int $shopId)
    {
        $this->infoShop = $infoShop;
        $this->shopId = $shopId;
    }

    public function getInfoShop(): InfoShop
    {
        return $this->infoShop;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }
}
