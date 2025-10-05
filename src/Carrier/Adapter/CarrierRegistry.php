<?php
/**
 * Registry resolving carrier adapters by code.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

use InvalidArgumentException;

final class CarrierRegistry
{
    /** @var array<string, CarrierAdapterInterface> */
    private array $adapters = [];

    public function __construct(iterable $adapters, private readonly ?CarrierAdapterInterface $fallbackAdapter = null)
    {
        foreach ($adapters as $adapter) {
            if (!$adapter instanceof CarrierAdapterInterface) {
                continue;
            }

            $code = strtoupper($adapter->getCode());
            $this->adapters[$code] = $adapter;
        }
    }

    public function get(string $code): CarrierAdapterInterface
    {
        $normalizedCode = strtoupper($code);

        if (isset($this->adapters[$normalizedCode])) {
            return $this->adapters[$normalizedCode];
        }

        if (null !== $this->fallbackAdapter) {
            return $this->fallbackAdapter;
        }

        throw new InvalidArgumentException(sprintf('No carrier adapter registered for code "%s"', $code));
    }
}
