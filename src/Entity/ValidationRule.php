<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;

/**
 * @noinspection PhpPropertyMayBeStaticInspection
 */
#[ORM\Entity(repositoryClass: ValidationRuleRepository::class)]
#[ORM\Table(name: self::TABLE_NAME)]
class ValidationRule
{
    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_validation_rule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_validation_rule', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 191)]
    private string $name = '';

    #[ORM\Column(name: 'priority', type: 'integer')]
    private int $priority = 0;

    #[ORM\Column(name: 'active', type: 'boolean')]
    private bool $active = true;

    // Shop context
    #[ORM\Column(name: 'shop_id', type: 'integer', nullable: true)]
    private ?int $shopId = null;

    #[ORM\Column(name: 'shop_group_id', type: 'integer', nullable: true)]
    private ?int $shopGroupId = null;

    // Conditions (stored as JSON arrays)
    #[ORM\Column(name: 'product_ids', type: 'json', nullable: true)]
    private ?array $productIds = null;

    #[ORM\Column(name: 'category_ids', type: 'json', nullable: true)]
    private ?array $categoryIds = null;

    #[ORM\Column(name: 'zone_ids', type: 'json', nullable: true)]
    private ?array $zoneIds = null;

    #[ORM\Column(name: 'country_ids', type: 'json', nullable: true)]
    private ?array $countryIds = null;

    #[ORM\Column(name: 'min_weight', type: 'float', nullable: true)]
    private ?float $minWeight = null;

    #[ORM\Column(name: 'max_weight', type: 'float', nullable: true)]
    private ?float $maxWeight = null;

    // Actions (stored as JSON arrays of carrier ids)
    #[ORM\Column(name: 'allow_ids', type: 'json', nullable: true)]
    private ?array $allowIds = null;

    #[ORM\Column(name: 'deny_ids', type: 'json', nullable: true)]
    private ?array $denyIds = null;

    #[ORM\Column(name: 'add_ids', type: 'json', nullable: true)]
    private ?array $addIds = null;

    #[ORM\Column(name: 'prefer_ids', type: 'json', nullable: true)]
    private ?array $preferIds = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getShopId(): ?int
    {
        return $this->shopId;
    }

    public function setShopId(?int $shopId): self
    {
        $this->shopId = $shopId;

        return $this;
    }

    public function getShopGroupId(): ?int
    {
        return $this->shopGroupId;
    }

    public function setShopGroupId(?int $shopGroupId): self
    {
        $this->shopGroupId = $shopGroupId;

        return $this;
    }

    public function getProductIds(): array
    {
        return $this->productIds ?? [];
    }

    public function setProductIds(?array $ids): self
    {
        $this->productIds = $ids;

        return $this;
    }

    public function getCategoryIds(): array
    {
        return $this->categoryIds ?? [];
    }

    public function setCategoryIds(?array $ids): self
    {
        $this->categoryIds = $ids;

        return $this;
    }

    public function getZoneIds(): array
    {
        return $this->zoneIds ?? [];
    }

    public function setZoneIds(?array $ids): self
    {
        $this->zoneIds = $ids;

        return $this;
    }

    public function getCountryIds(): array
    {
        return $this->countryIds ?? [];
    }

    public function setCountryIds(?array $ids): self
    {
        $this->countryIds = $ids;

        return $this;
    }

    public function getMinWeight(): ?float
    {
        return $this->minWeight;
    }

    public function setMinWeight(?float $minWeight): self
    {
        $this->minWeight = $minWeight;

        return $this;
    }

    public function getMaxWeight(): ?float
    {
        return $this->maxWeight;
    }

    public function setMaxWeight(?float $maxWeight): self
    {
        $this->maxWeight = $maxWeight;

        return $this;
    }

    public function getAllowIds(): array
    {
        return $this->allowIds ?? [];
    }

    public function setAllowIds(?array $ids): self
    {
        $this->allowIds = $ids;

        return $this;
    }

    public function getDenyIds(): array
    {
        return $this->denyIds ?? [];
    }

    public function setDenyIds(?array $ids): self
    {
        $this->denyIds = $ids;

        return $this;
    }

    public function getAddIds(): array
    {
        return $this->addIds ?? [];
    }

    public function setAddIds(?array $ids): self
    {
        $this->addIds = $ids;

        return $this;
    }

    public function getPreferIds(): array
    {
        return $this->preferIds ?? [];
    }

    public function setPreferIds(?array $ids): self
    {
        $this->preferIds = $ids;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
