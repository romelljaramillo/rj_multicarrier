<?php
/**
 * Doctrine entity mapping the legacy carrier log table.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;
use \Roanja\Module\RjMulticarrier\Repository\LogEntryRepository;

#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
#[ORM\Table(name: _DB_PREFIX_ . 'rj_multicarrier_log')]
class LogEntry
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_carrier_log', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 250)]
    private string $name;

    #[ORM\Column(name: 'id_order', type: 'integer')]
    private int $orderId;

    #[ORM\Column(name: 'id_shop', type: 'integer', options: ['unsigned' => true])]
    private int $shopId = 0;

    #[ORM\Column(name: 'request', type: 'text', nullable: true)]
    private ?string $requestPayload = null;

    #[ORM\Column(name: 'response', type: 'text', nullable: true)]
    private ?string $responsePayload = null;

    public function __construct(string $name, int $orderId, string $requestPayload = '', ?string $responsePayload = null, ?int $shopId = null)
    {
        $this->name = $name;
        $this->orderId = $orderId;
        $this->requestPayload = $requestPayload !== '' ? $requestPayload : null;
        $this->responsePayload = $responsePayload;
        $this->shopId = $shopId ?? 0;
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

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function setShopId(int $shopId): self
    {
        $this->shopId = $shopId;

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
}
