<?php
/**
 * Simple trait to expose created/updated timestamps matching legacy columns.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity\Traits;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

trait TimestampableTrait
{
    #[ORM\Column(name: 'date_add', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'date_upd', type: 'datetime', nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt ? DateTimeImmutable::createFromInterface($createdAt) : null;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt ? DateTimeImmutable::createFromInterface($updatedAt) : null;

        return $this;
    }
}
