<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Handler;

use Roanja\Module\RjMulticarrier\Domain\Carrier\Query\GetCarrierForView;
use Roanja\Module\RjMulticarrier\Domain\Carrier\View\CarrierDetailView;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

final class GetCarrierForViewHandler
{
    public function __construct(private CarrierRepository $carrierRepository)
    {
    }

    public function handle(GetCarrierForView $query): ?CarrierDetailView
    {
        $carrier = $this->carrierRepository->find($query->getId());

        if (null === $carrier) {
            return null;
        }

        $createdAt = $carrier->getCreatedAt();
        $updatedAt = $carrier->getUpdatedAt();

        return new CarrierDetailView(
            $carrier->getId(),
            $carrier->getName(),
            $carrier->getShortName(),
            $this->buildIconUrl($carrier->getIcon()),
            $carrier->getShopIds(),
            $createdAt ? $createdAt->format('Y-m-d H:i:s') : null,
            $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null
        );
    }

    private function buildIconUrl(?string $fileName): ?string
    {
        if (null === $fileName || '' === $fileName) {
            return null;
        }

        return _MODULE_DIR_ . 'rj_multicarrier/var/icons/' . $fileName;
    }
}
