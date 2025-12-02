<?php
/**
 * Exception thrown when trying to install a carrier adapter that already exists.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier\Exception;

use Roanja\Module\RjMulticarrier\Entity\Carrier;
use RuntimeException;

final class CarrierAlreadyInstalledException extends RuntimeException
{
    public function __construct(private readonly Carrier $carrier)
    {
        parent::__construct(sprintf('Carrier %s is already installed.', (string) $carrier->getShortName()));
    }

    public function getCarrier(): Carrier
    {
        return $this->carrier;
    }
}
