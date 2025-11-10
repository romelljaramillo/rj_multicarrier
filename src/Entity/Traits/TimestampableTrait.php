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
    #[ORM\Column(name: 'date_add', type: 'datetime', nullable: false)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(name: 'date_upd', type: 'datetime', nullable: false)]
    private DateTimeInterface $updatedAt;

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = DateTimeImmutable::createFromInterface($createdAt);

        return $this;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = DateTimeImmutable::createFromInterface($updatedAt);

        return $this;
    }
}
