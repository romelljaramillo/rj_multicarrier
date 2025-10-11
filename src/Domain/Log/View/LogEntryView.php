<?php
/**
 * Value object representing a log entry ready for presentation layers.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\View;

use Roanja\Module\RjMulticarrier\Entity\LogEntry;

final class LogEntryView
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $name,
        private readonly ?int $orderId,
        private readonly ?string $createdAt,
        private readonly ?string $updatedAt,
        private readonly ?string $requestPayload,
        private readonly ?string $responsePayload
    ) {
    }

    public static function fromEntity(LogEntry $log): self
    {
        return new self(
            $log->getId(),
            $log->getName(),
            $log->getOrderId(),
            $log->getCreatedAt()?->format('Y-m-d H:i:s'),
            $log->getUpdatedAt()?->format('Y-m-d H:i:s'),
            $log->getRequestPayload(),
            $log->getResponsePayload()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'orderId' => $this->orderId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'requestPayload' => $this->requestPayload,
            'responsePayload' => $this->responsePayload,
        ];
    }

    public function toCsvRow(): array
    {
        return [
            $this->id,
            $this->name,
            $this->orderId,
            $this->createdAt,
            $this->updatedAt,
            $this->requestPayload,
            $this->responsePayload,
        ];
    }
}
