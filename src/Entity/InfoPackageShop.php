<?php
/**
 * Mapping entity between info packages and shop ids (legacy table).
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_infopackage_shop')]
class InfoPackageShop
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: InfoPackage::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_infopackage', referencedColumnName: 'id_infopackage', nullable: false, onDelete: 'CASCADE')]
    private InfoPackage $infoPackage;

    #[ORM\Id]
    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $shopId;

    public function __construct(InfoPackage $infoPackage, int $shopId)
    {
        $this->infoPackage = $infoPackage;
        $this->shopId = $shopId;
    }

    public function getInfoPackage(): InfoPackage
    {
        return $this->infoPackage;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }
}
