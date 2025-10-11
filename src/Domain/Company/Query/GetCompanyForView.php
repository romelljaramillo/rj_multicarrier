<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Query;

final class GetCompanyForView
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
