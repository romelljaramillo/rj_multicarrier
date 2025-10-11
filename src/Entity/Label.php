<?php
/**
 * Doctrine entity for stored shipment labels.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\LabelRepository::class)]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_label')]
class Label
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_label', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(name: 'id_shipment', referencedColumnName: 'id_shipment', nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(name: 'package_id', type: 'string', length: 50, nullable: true)]
    private ?string $packageId = null;

    #[ORM\Column(name: 'tracker_code', type: 'string', length: 100, nullable: true)]
    private ?string $trackerCode = null;

    #[ORM\Column(name: 'label_type', type: 'string', length: 100, nullable: true)]
    private ?string $labelType = null;

    #[ORM\Column(name: 'pdf', type: 'text', nullable: true)]
    private ?string $pdf = null;

    #[ORM\Column(name: 'print', type: 'boolean')]
    private bool $printed = false;

    public function __construct(Shipment $shipment)
    {
        $this->shipment = $shipment;
        $this->shops = new ArrayCollection();
    }

    /**
     * Legacy mapping to shops.
     *
     * @var Collection<int, LabelShop>
     */
    #[ORM\OneToMany(targetEntity: LabelShop::class, mappedBy: 'label')]
    private Collection $shops;

    /**
     * @return Collection<int, LabelShop>
     */
    public function getShops(): Collection
    {
        return $this->shops;
    }

    public function addShop(LabelShop $shop): self
    {
        if (!$this->shops->contains($shop)) {
            $this->shops->add($shop);
            $shop->setLabel($this);
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShipment(): Shipment
    {
        return $this->shipment;
    }

    public function setShipment(Shipment $shipment): self
    {
        $this->shipment = $shipment;

        return $this;
    }

    public function getPackageId(): ?string
    {
        return $this->packageId;
    }

    public function setPackageId(?string $packageId): self
    {
        $this->packageId = $packageId;

        return $this;
    }

    public function getTrackerCode(): ?string
    {
        return $this->trackerCode;
    }

    public function setTrackerCode(?string $trackerCode): self
    {
        $this->trackerCode = $trackerCode;

        return $this;
    }

    public function getLabelType(): ?string
    {
        return $this->labelType;
    }

    public function setLabelType(?string $labelType): self
    {
        $this->labelType = $labelType;

        return $this;
    }

    public function getPdf(): ?string
    {
        return $this->pdf;
    }

    public function setPdf(?string $pdf): self
    {
        $this->pdf = $pdf;

        return $this;
    }

    public function isPrinted(): bool
    {
        return $this->printed;
    }

    public function setPrinted(bool $printed): self
    {
        $this->printed = $printed;

        return $this;
    }
}
