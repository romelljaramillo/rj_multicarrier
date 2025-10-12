<?php
/**
 * Doctrine entity wrapping legacy shipment records.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\ShipmentRepository::class)]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_shipment')]
class Shipment
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_shipment', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'id_order', type: 'integer')]
    private int $orderId;

    #[ORM\Column(name: 'reference_order', type: 'string', length: 100, nullable: true)]
    private ?string $orderReference = null;

    #[ORM\Column(name: 'num_shipment', type: 'string', length: 100, nullable: true)]
    private ?string $shipmentNumber = null;

    #[ORM\ManyToOne(targetEntity: Carrier::class)]
    #[ORM\JoinColumn(name: 'id_carrier', referencedColumnName: 'id_carrier', nullable: true, onDelete: 'SET NULL')]
    private ?Carrier $carrier = null;

    #[ORM\ManyToOne(targetEntity: InfoPackage::class)]
    #[ORM\JoinColumn(name: 'id_infopackage', referencedColumnName: 'id_infopackage', nullable: false, onDelete: 'CASCADE')]
    private InfoPackage $infoPackage;

    #[ORM\Column(name: 'account', type: 'string', length: 100, nullable: true)]
    private ?string $account = null;

    #[ORM\Column(name: 'product', type: 'string', length: 100, nullable: true)]
    private ?string $product = null;

    #[ORM\Column(name: 'request', type: 'text', nullable: true)]
    private ?string $requestPayload = null;

    #[ORM\Column(name: 'response', type: 'text', nullable: true)]
    private ?string $responsePayload = null;

    #[ORM\Column(name: '`delete`', type: 'boolean')]
    private bool $deleted = false;

    /**
     * @var Collection<int, ShipmentShop> Legacy mapping to shops.
     */
    #[ORM\OneToMany(targetEntity: ShipmentShop::class, mappedBy: 'shipment')]
    private Collection $shops;

    public function __construct(int $orderId, InfoPackage $infoPackage)
    {
        $this->orderId = $orderId;
        $this->infoPackage = $infoPackage;
        $this->shops = new ArrayCollection();
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

    public function getOrderReference(): ?string
    {
        return $this->orderReference;
    }

    public function setOrderReference(?string $orderReference): self
    {
        $this->orderReference = $orderReference;

        return $this;
    }

    public function getShipmentNumber(): ?string
    {
        return $this->shipmentNumber;
    }

    public function setShipmentNumber(?string $shipmentNumber): self
    {
        $this->shipmentNumber = $shipmentNumber;

        return $this;
    }

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function setCarrier(?Carrier $carrier): self
    {
        $this->carrier = $carrier;

        return $this;
    }

    public function getInfoPackage(): InfoPackage
    {
        return $this->infoPackage;
    }

    public function setInfoPackage(InfoPackage $infoPackage): self
    {
        $this->infoPackage = $infoPackage;

        return $this;
    }

    public function getAccount(): ?string
    {
        return $this->account;
    }

    public function setAccount(?string $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    public function setProduct(?string $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getRequestPayload(): ?string
    {
        return $this->requestPayload;
    }

    public function setRequestPayload(?string $requestPayload): self
    {
        $this->requestPayload = $requestPayload;

        return $this;
    }

    public function getResponsePayload(): ?string
    {
        return $this->responsePayload;
    }

    public function setResponsePayload(?string $responsePayload): self
    {
        $this->responsePayload = $responsePayload;

        return $this;
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

    /**
     * @return Collection<int, ShipmentShop>
     */
    public function getShops(): Collection
    {
        return $this->shops;
    }

    public function addShop(ShipmentShop $shop): self
    {
        if (!$this->shops->contains($shop)) {
            $this->shops->add($shop);
            $shop->setShipment($this);
        }

        return $this;
    }
}
