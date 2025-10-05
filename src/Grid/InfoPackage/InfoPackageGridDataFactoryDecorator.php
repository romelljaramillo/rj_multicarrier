<?php
/**
 * Grid data decorator to enrich info package rows with CSRF tokens.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoPackage;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class InfoPackageGridDataFactoryDecorator implements GridDataFactoryInterface
{
    public function __construct(
        private readonly GridDataFactoryInterface $infoPackageDoctrineGridDataFactory,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    public function getData(SearchCriteriaInterface $searchCriteria)
    {
        $gridData = $this->infoPackageDoctrineGridDataFactory->getData($searchCriteria);

        $recordsWithTokens = [];
        foreach ($gridData->getRecords() as $record) {
            $id = (int) ($record['id_infopackage'] ?? 0);
            if ($id > 0) {
                $record['generate_token'] = $this->csrfTokenManager
                    ->getToken('generate_info_package_' . $id)
                    ->getValue();
            }

            $recordsWithTokens[] = $record;
        }

        return new GridData(
            new RecordCollection($recordsWithTokens),
            $gridData->getRecordsTotal(),
            $gridData->getQuery()
        );
    }
}
