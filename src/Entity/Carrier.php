<?php
/**
 * Doctrine entity representing module carriers.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\CarrierRepository::class)]
#[ORM\Table(name: self::TABLE_NAME)]
class Carrier
{
    use TimestampableTrait;

    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_carrier';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_carrier', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 50)]
    private string $name;

    #[ORM\Column(name: 'shortname', type: 'string', length: 4)]
    private string $shortName;

    #[ORM\Column(name: 'icon', type: 'string', length: 250, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'supports_api', type: 'boolean', options: ['default' => false])]
    private bool $supportsApi = false;

    #[ORM\Column(name: 'adapter_service', type: 'string', length: 191, nullable: true)]
    private ?string $adapterService = null;

    #[ORM\Column(name: 'configuration_schema', type: 'json', nullable: true)]
    private ?array $configurationSchema = null;

    #[ORM\Column(name: 'default_payload', type: 'json', nullable: true)]
    private ?array $defaultPayload = null;

    #[ORM\Column(name: '`delete`', type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var Collection<int, CarrierShop>
     */
    #[ORM\OneToMany(mappedBy: 'carrier', targetEntity: CarrierShop::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $shops;

    public function __construct(string $name, string $shortName)
    {
        $this->name = $name;
        $this->shortName = $shortName;
        $this->shops = new ArrayCollection();
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function supportsApi(): bool
    {
        return $this->supportsApi;
    }

    public function setSupportsApi(bool $supportsApi): self
    {
        $this->supportsApi = $supportsApi;

        return $this;
    }

    public function getAdapterService(): ?string
    {
        return $this->adapterService;
    }

    public function setAdapterService(?string $adapterService): self
    {
        $this->adapterService = $adapterService ? trim($adapterService) : null;

        return $this;
    }

    public function getConfigurationSchema(): array
    {
        return $this->configurationSchema ?? [];
    }

    public function setConfigurationSchema(?array $configurationSchema): self
    {
        $this->configurationSchema = empty($configurationSchema) ? null : $configurationSchema;

        return $this;
    }

    public function getDefaultPayload(): array
    {
        return $this->defaultPayload ?? [];
    }

    public function setDefaultPayload(?array $defaultPayload): self
    {
        $this->defaultPayload = empty($defaultPayload) ? null : $defaultPayload;

        return $this;
    }

    /**
     * @return Collection<int, CarrierShop>
     */
    public function getShops(): Collection
    {
        return $this->shops;
    }

    public function addShop(CarrierShop $shop): self
    {
        if (! $this->shops->contains($shop)) {
            $this->shops->add($shop);
        }

        return $this;
    }

    public function removeShop(CarrierShop $shop): self
    {
        if ($this->shops->contains($shop)) {
            $this->shops->removeElement($shop);
        }

        return $this;
    }

    /**
     * Utility: return an array of shop ids where this carrier is available
     *
     * @return int[]
     */
    public function getShopIds(): array
    {
        $ids = [];
        foreach ($this->shops as $shop) {
            $ids[] = $shop->getIdShop();
        }

        return $ids;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function markDeleted(): self
    {
        $this->deleted = true;

        return $this;
    }

    public function restore(): self
    {
        $this->deleted = false;

        return $this;
    }
}
