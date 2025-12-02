<?php
/**
 * Handler that resolves carrier options for manifest generation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetManifestCarrierOptions;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ManifestCarrierView;
use Roanja\Module\RjMulticarrier\Entity\Shipment;
use Roanja\Module\RjMulticarrier\Service\Shipment\ManifestCarrierLabelResolver;

final class GetManifestCarrierOptionsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ManifestCarrierLabelResolver $labelResolver
    ) {
    }

    /**
     * @return ManifestCarrierView[]
     */
    public function handle(GetManifestCarrierOptions $query): array
    {
        $dateFrom = $query->getDateFrom();
        $dateTo = $query->getDateTo();

        $results = $this->entityManager->createQueryBuilder()
            ->select('info.referenceCarrierId AS referenceCarrierId', 'COUNT(DISTINCT shipment.id) AS shipmentCount')
            ->from(Shipment::class, 'shipment')
            ->innerJoin('shipment.infoPackage', 'info')
            ->andWhere('shipment.deleted = false')
            ->andWhere('info.referenceCarrierId IS NOT NULL')
            ->andWhere('shipment.createdAt BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->groupBy('info.referenceCarrierId')
            ->orderBy('info.referenceCarrierId', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $referenceIds = [];
        $counts = [];

        foreach ($results as $row) {
            $referenceId = isset($row['referenceCarrierId']) ? (int) $row['referenceCarrierId'] : 0;
            if ($referenceId <= 0) {
                continue;
            }

            $referenceIds[] = $referenceId;
            $counts[$referenceId] = isset($row['shipmentCount']) ? (int) $row['shipmentCount'] : 0;
        }

        $labels = $this->labelResolver->resolveLabels($referenceIds);

        $views = [];
        foreach (array_unique($referenceIds) as $referenceId) {
            $label = $labels[$referenceId] ?? sprintf('Carrier #%d', $referenceId);
            $views[] = new ManifestCarrierView($referenceId, $label, $counts[$referenceId] ?? 0);
        }

        usort($views, static function (ManifestCarrierView $left, ManifestCarrierView $right): int {
            return strcasecmp($left->getLabel(), $right->getLabel());
        });

        return $views;
    }
}
