<?php
/**
 * Command to persist carrier logs through Doctrine.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Command;

/**
 * @psalm-immutable
 */
final class CreateLogEntryCommand
{
    public function __construct(
        private readonly string $name,
        private readonly int $orderId,
        private readonly string $request,
        private readonly ?string $response,
        private readonly ?int $shopId = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getRequest(): string
    {
        return $this->request;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function getShopId(): ?int
    {
        return null === $this->shopId ? null : (int) $this->shopId;
    }
}
