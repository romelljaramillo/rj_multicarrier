<?php
/**
 * Doctrine entity representing shipment package information.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository::class)]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_infopackage')]
class InfoPackage
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_infopackage', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'id_order', type: 'integer')]
    private int $orderId;

    #[ORM\Column(name: 'id_reference_carrier', type: 'integer')]
    private int $referenceCarrierId;

    #[ORM\ManyToOne(targetEntity: TypeShipment::class)]
    #[ORM\JoinColumn(name: 'id_type_shipment', referencedColumnName: 'id_type_shipment', nullable: false, onDelete: 'CASCADE')]
    private TypeShipment $typeShipment;

    /**
     * Legacy mapping to shops.
     *
     * @var Collection<int, InfoPackageShop>
     */
    #[ORM\OneToMany(targetEntity: InfoPackageShop::class, mappedBy: 'infoPackage')]
    private Collection $shops;

    #[ORM\Column(name: 'quantity', type: 'integer')]
    private int $quantity;

    #[ORM\Column(name: 'weight', type: 'float')]
    private float $weight;

    #[ORM\Column(name: 'length', type: 'float', nullable: true)]
    private ?float $length = null;

    #[ORM\Column(name: 'width', type: 'float', nullable: true)]
    private ?float $width = null;

    #[ORM\Column(name: 'height', type: 'float', nullable: true)]
    private ?float $height = null;

    #[ORM\Column(name: 'cash_ondelivery', type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?string $cashOnDelivery = null;

    #[ORM\Column(name: 'message', type: 'string', length: 255, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'hour_from', type: 'time', nullable: true)]
    private ?DateTimeInterface $hourFrom = null;

    #[ORM\Column(name: 'hour_until', type: 'time', nullable: true)]
    private ?DateTimeInterface $hourUntil = null;

    #[ORM\Column(name: 'retorno', type: 'integer', nullable: true)]
    private ?int $retorno = null;

    #[ORM\Column(name: 'rcs', type: 'boolean', options: ['default' => 0])]
    private bool $rcs = false;

    #[ORM\Column(name: 'vsec', type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?string $vsec = null;

    #[ORM\Column(name: 'dorig', type: 'string', length: 255, nullable: true)]
    private ?string $dorig = null;

    public function __construct(int $orderId, int $referenceCarrierId, TypeShipment $typeShipment, int $quantity, float $weight)
    {
        $this->orderId = $orderId;
        $this->referenceCarrierId = $referenceCarrierId;
        $this->typeShipment = $typeShipment;
        $this->quantity = $quantity;
        $this->weight = $weight;
        $this->shops = new ArrayCollection();
    }

    /**
     * @return Collection<int, InfoPackageShop>
     */
    public function getShops(): Collection
    {
        return $this->shops;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getReferenceCarrierId(): int
    {
        return $this->referenceCarrierId;
    }

    public function setReferenceCarrierId(int $referenceCarrierId): self
    {
        $this->referenceCarrierId = $referenceCarrierId;

        return $this;
    }

    public function getTypeShipment(): TypeShipment
    {
        return $this->typeShipment;
    }

    public function setTypeShipment(TypeShipment $typeShipment): self
    {
        $this->typeShipment = $typeShipment;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function setLength(?float $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function setWidth(?float $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(?float $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getCashOnDelivery(): ?string
    {
        return $this->cashOnDelivery;
    }

    public function setCashOnDelivery(?string $cashOnDelivery): self
    {
        $this->cashOnDelivery = $cashOnDelivery;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getHourFrom(): ?DateTimeInterface
    {
        return $this->hourFrom;
    }

    public function setHourFrom(?DateTimeInterface $hourFrom): self
    {
        $this->hourFrom = $hourFrom;

        return $this;
    }

    public function getHourUntil(): ?DateTimeInterface
    {
        return $this->hourUntil;
    }

    public function setHourUntil(?DateTimeInterface $hourUntil): self
    {
        $this->hourUntil = $hourUntil;

        return $this;
    }

    public function getRetorno(): ?int
    {
        return $this->retorno;
    }

    public function setRetorno(?int $retorno): self
    {
        $this->retorno = $retorno;

        return $this;
    }

    public function isRcsEnabled(): bool
    {
        return $this->rcs;
    }

    public function setRcsEnabled(bool $rcs): self
    {
        $this->rcs = $rcs;

        return $this;
    }

    public function getVsec(): ?string
    {
        return $this->vsec;
    }

    public function setVsec(?string $vsec): self
    {
        $this->vsec = $vsec;

        return $this;
    }

    public function getDorig(): ?string
    {
        return $this->dorig;
    }

    public function setDorig(?string $dorig): self
    {
        $this->dorig = $dorig;

        return $this;
    }
}
