<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler;

use Roanja\Module\RjMulticarrier\Domain\InfoShop\Exception\InfoShopNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Query\GetInfoShopForEdit;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\View\InfoShopDetailView;
use Roanja\Module\RjMulticarrier\Entity\InfoShop;
use Roanja\Module\RjMulticarrier\Repository\InfoShopRepository;

final class GetInfoShopForEditHandler
{
    public function __construct(private readonly InfoShopRepository $infoShopRepository)
    {
    }

    public function handle(GetInfoShopForEdit $query): InfoShopDetailView
    {
        $infoShop = $this->infoShopRepository->find($query->getInfoShopId());

        if (!$infoShop instanceof InfoShop) {
            throw InfoShopNotFoundException::forId($query->getInfoShopId());
        }

        return $this->mapToView($infoShop);
    }

    private function mapToView(InfoShop $infoShop): InfoShopDetailView
    {
        $shops = [];
        foreach ($infoShop->getShops() as $mapping) {
            if (method_exists($mapping, 'getShopId')) {
                $shops[] = (int) $mapping->getShopId();
            }
        }

        $createdAt = $infoShop->getCreatedAt();
        $updatedAt = $infoShop->getUpdatedAt();

        return new InfoShopDetailView(
            $infoShop->getId() ?? 0,
            $infoShop->getFirstName(),
            $infoShop->getLastName(),
            $infoShop->getCompany(),
            $infoShop->getAdditionalName(),
            $infoShop->getCountryId(),
            $infoShop->getState(),
            $infoShop->getCity(),
            $infoShop->getStreet(),
            $infoShop->getStreetNumber(),
            $infoShop->getPostcode(),
            $infoShop->getAdditionalAddress(),
            $this->normalizeBusinessFlag($infoShop->getIsBusinessFlag()),
            $infoShop->getEmail(),
            $infoShop->getPhone(),
            $infoShop->getVatNumber(),
            $shops,
            $createdAt ? $createdAt->format('Y-m-d H:i:s') : null,
            $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null,
            $infoShop->isActive()
        );
    }

    private function normalizeBusinessFlag(?string $flag): ?bool
    {
        if (null === $flag || '' === trim($flag)) {
            return null;
        }

        return in_array(trim($flag), ['1', 'true', 'on', 'yes'], true);
    }
}
