<?php
/**
 * Mapping entity between labels and shop ids (legacy table).
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_label_shop')]
class LabelShop
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Label::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_label', referencedColumnName: 'id_label', nullable: false, onDelete: 'CASCADE')]
    private Label $label;

    #[ORM\Id]
    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $shopId;

    public function __construct(Label $label, int $shopId)
    {
        $this->label = $label;
        $this->shopId = $shopId;
    }

    public function getLabel(): Label
    {
        return $this->label;
    }

    public function setLabel(Label $label): void
    {
        $this->label = $label;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }
}
