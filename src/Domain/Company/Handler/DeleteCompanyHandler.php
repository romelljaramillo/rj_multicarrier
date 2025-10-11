<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Company\Command\DeleteCompanyCommand;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Throwable;

final class DeleteCompanyHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Handle the delete company command.
     *
     * Throws \RuntimeException on failure or when references exist.
     */
    public function handle(DeleteCompanyCommand $command): void
    {
        $companyId = $command->getCompanyId();

        /** @var Company|null $company */
        $company = $this->em->find(Company::class, $companyId);
        if (null === $company) {
            throw new \RuntimeException(sprintf('Company with id %d not found.', $companyId));
        }

        // DBAL connection for simple scalar checks
        $connection = $this->em->getConnection();

        $shipmentCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'rj_multicarrier_shipment` WHERE id_carrier_company = ?', [$companyId]);
        if ($shipmentCount > 0) {
            throw new \RuntimeException('Hay envíos asociados a esta compañía.');
        }

        $typeCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'rj_multicarrier_type_shipment` WHERE id_carrier_company = ?', [$companyId]);
        if ($typeCount > 0) {
            throw new \RuntimeException('Hay tipos de envío asociados a esta compañía.');
        }

        $configCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'rj_multicarrier_configuration` WHERE id_carrier_company = ?', [$companyId]);
        if ($configCount > 0) {
            throw new \RuntimeException('Hay configuraciones asociadas a esta compañía.');
        }

        try {
            $company->markDeleted();

            // remove icon and thumbnail files (same convention as controller)
            $icon = $company->getIcon();
            if (!empty($icon)) {
                $this->removeIconFiles($icon);
            }

            $this->em->persist($company);
            $this->em->flush();
        } catch (Throwable $e) {
            throw new \RuntimeException('No se pudo eliminar la compañía: ' . $e->getMessage());
        }
    }

    private function makeThumbName(string $fileName): string
    {
        $pos = strrpos($fileName, '.');
        if (false === $pos) {
            return $fileName . '_thumb';
        }

        $base = substr($fileName, 0, $pos);
        $ext = substr($fileName, $pos + 1);

        return $base . '_thumb.' . $ext;
    }

    private function removeIconFiles(string $fileName): void
    {
        $baseDir = (defined('IMG_ICON_COMPANY_DIR') ? IMG_ICON_COMPANY_DIR : (_PS_MODULE_DIR_ . 'rj_multicarrier/var/icons/'));
        $filePath = $baseDir . $fileName;
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $thumb = $baseDir . $this->makeThumbName($fileName);
        if (is_file($thumb)) {
            @unlink($thumb);
        }
    }
}
