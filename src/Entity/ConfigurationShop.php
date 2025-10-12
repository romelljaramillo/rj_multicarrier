<?php
/**
 * Mapping entity between configuration and shop context.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_configuration_shop')]
class ConfigurationShop
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Configuration::class, inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'id_configuration', referencedColumnName: 'id_configuration', nullable: false, onDelete: 'CASCADE')]
    private Configuration $configuration;

    #[ORM\Id]
    #[ORM\Column(name: 'id_shop', type: 'integer')]
    private int $shopId;

    public function __construct(Configuration $configuration, int $shopId)
    {
        $this->configuration = $configuration;
        $this->shopId = $shopId;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }
}
