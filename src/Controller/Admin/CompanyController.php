<?php

/**
 * Symfony controller for managing carrier companies.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Grid\Company\CompanyGridFactory;
use Roanja\Module\RjMulticarrier\Grid\Company\CompanyFilters;
use Roanja\Module\RjMulticarrier\Grid\Company\CompanyGridDefinitionFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompanyForView;
use Roanja\Module\RjMulticarrier\Domain\Company\View\CompanyDetailView;
use Roanja\Module\RjMulticarrier\Domain\Company\Command\DeleteCompanyCommand;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command\DeleteCompanyConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command\UpsertCompanyConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query\GetCompanyConfigurationsForCompany;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\View\CompanyConfigurationView;


final class CompanyController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(private readonly CompanyGridFactory $gridFactory)
    {
    }

    public function indexAction(Request $request, CompanyFilters $filters): Response
    {
        $filters->setNeedsToBePersisted(false);
        $grid = $this->gridFactory->getGrid($filters);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/company/index.html.twig', [
            'layoutTitle' => $this->translate('Carrier companies'),
            'companyGrid' => $this->presentGrid($grid),
        ]);
    }

    private function buildFilters(Request $request): CompanyFilters
    {
        $defaults = CompanyFilters::getDefaults();
        $scopedParameters = $this->extractScopedParameters($request, 'rj_multicarrier_company');

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', $defaults['filters']),
        ]);

        $filters = new CompanyFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function viewAction(int $id): JsonResponse
    {
        /** Use query bus to fetch a Company detail view (consistent with LogController) */
        $companyView = $this->getQueryBus()->handle(new GetCompanyForView($id));

        if (null === $companyView) {
            return $this->json([
                'message' => $this->translate('La compañía solicitada no existe.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($companyView->toArray());
    }

    public function createAction(Request $request): Response
    {
        $configurationPayload = $this->parseConfigurationEntries($request);

        if ($request->isMethod('POST')) {
            $name = (string) $request->request->get('name', '');
            $shortName = (string) $request->request->get('shortName', '');

            if ('' === $name || '' === $shortName) {
                $this->addFlash('error', $this->translate('Nombre y shortName son obligatorios.'));

                return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
            }

            // handle uploaded icon: move file to module dir and generate thumb
            $iconFilename = null;
            $iconFile = $request->files->get('icon');
            if ($iconFile instanceof UploadedFile && $iconFile->isValid()) {
                // Try to move file and create thumbnail. Returns filename or null
                $iconFilename = $this->moveUploadedIcon($iconFile);
                if (null === $iconFilename) {
                    $this->addFlash('warning', $this->translate('El icon no pudo subirse.'));
                }
            }

            $shopIds = $request->request->get('shops', []);
            if (!is_array($shopIds)) {
                $shopIds = [];
            }

            $command = new \Roanja\Module\RjMulticarrier\Domain\Company\Command\CreateCompanyCommand(
                $name,
                $shortName,
                $iconFilename,
                array_map('intval', $shopIds)
            );

            try {
                /** @var Company $createdCompany */
                $createdCompany = $this->getCommandBus()->handle($command);

                if ($createdCompany instanceof Company) {
                    $this->syncCompanyConfigurations($createdCompany->getId() ?? 0, $configurationPayload);
                }

                $this->addFlash('success', $this->translate('Compañía creada correctamente.'));

                return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
            } catch (\Throwable $e) {
                $this->addFlash('error', $this->translate('No se pudo crear la compañía: %s', [$e->getMessage()]));
                return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/company/form.html.twig', [
            'company' => null,
            'action' => 'create',
            'iconUrl' => null,
            'shopChoices' => $this->getAvailableShops(),
            'selectedShops' => [],
            'configurationEntries' => [],
        ]);
    }

    public function editAction(Request $request, int $id): Response
    {
        $companyData = $this->getCompanyData($id);
        $companyId = $companyData['id'];

        $configurationPayload = $this->parseConfigurationEntries($request);

        if ($request->isMethod('POST')) {
            $name = (string) $request->request->get('name', $companyData['name'] ?? '');
            $shortName = (string) $request->request->get('shortName', $companyData['shortName'] ?? '');

            // handle uploaded icon: move file to module dir and generate thumb
            $iconFilename = null;
            $iconFile = $request->files->get('icon');
            if ($iconFile instanceof UploadedFile && $iconFile->isValid()) {
                $iconFilename = $this->moveUploadedIcon($iconFile);
                if (null === $iconFilename) {
                    $this->addFlash('warning', $this->translate('El icon no pudo subirse.'));
                }
            }

            $shopIds = $request->request->get('shops', []);
            if (!is_array($shopIds)) {
                $shopIds = [];
            }

            $command = new \Roanja\Module\RjMulticarrier\Domain\Company\Command\UpdateCompanyCommand(
                $companyId,
                $name,
                $shortName,
                $iconFilename,
                array_map('intval', $shopIds)
            );

            try {
                $this->getCommandBus()->handle($command);

                $this->syncCompanyConfigurations($companyId, $configurationPayload);

                $this->addFlash('success', $this->translate('Compañía actualizada correctamente.'));

                return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
            } catch (\Throwable $e) {
                $this->addFlash('error', $this->translate('No se pudo actualizar la compañía: %s', [$e->getMessage()]));
                return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/company/form.html.twig', [
            'company' => $companyData,
            'action' => 'edit',
            'iconUrl' => $companyData['icon'] ?? null,
            'shopChoices' => $this->getAvailableShops(),
            'selectedShops' => $companyData['shops'] ?? [],
            'configurationEntries' => $this->getCompanyConfigurationEntries($companyId),
        ]);
    }

    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        try {
            $this->getCommandBus()->handle(new DeleteCompanyCommand($id));
            $this->addFlash('success', $this->translate('Compañía eliminada correctamente.'));
        } catch (Throwable $e) {
            $this->addFlash('warning', $this->translate($e->getMessage()));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteBulkAction(Request $request): RedirectResponse
    {
        $companyIds = $this->getBulkCompanyIds($request);

        if (empty($companyIds)) {
            $this->addFlash('warning', $this->translate('No se seleccionaron registros para eliminar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
        }

        try {
            $deleted = 0;
            $failed = [];

            foreach ($companyIds as $id) {
                try {
                    $this->getCommandBus()->handle(new DeleteCompanyCommand($id));
                    $deleted++;
                } catch (Throwable $e) {
                    $failed[] = $id;
                }
            }

            if ($deleted > 0) {
                $this->addFlash('success', $this->translate('%count% registros eliminados.', ['%count%' => $deleted]));
            }

            if (!empty($failed)) {
                $this->addFlash('warning', $this->translate('%count% registros no se pudieron eliminar por referencias.', ['%count%' => count($failed)]));
            }
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudo completar la eliminación masiva.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportCsvAction(Request $request): Response
    {
        // If the client submitted selected ids via bulk column, prefer exporting those
        $selectedIds = $this->getBulkCompanyIds($request);

        if (!empty($selectedIds)) {
            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: exportCsvAction - exporting selected ids', [
                    'selected_ids' => $selectedIds,
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                ]);
            }

            $query = new \Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompaniesForGrid(['ids' => $selectedIds]);
            $companies = $this->getQueryBus()->handle($query);
        } else {
            $filters = $this->buildFilters($request);
            $filters->set('limit', 0);
            $filters->set('offset', 0);

            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: exportCsvAction request', [
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                    'resolved_filters' => $filters->getFilters(),
                ]);
            }

            $companies = $this->getQueryBus()->handle(new \Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompaniesForGrid($filters->getFilters()));
        }

        $fileName = sprintf('rj_multicarrier_companies_%s.csv', date('Ymd_His'));

        return $this->createCompanyCsvResponse($companies, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $companyIds = $this->getBulkCompanyIds($request);

        if (empty($companyIds)) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un registro para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
        }
        // Temporary debug: log selected ids and request payload
        if ($this->has('logger')) {
            $this->get('logger')->debug('rj_multicarrier: exportSelectedCsvAction request', [
                'request_query' => $request->query->all(),
                'request_post' => $request->request->all(),
                'selected_ids' => $companyIds,
            ]);
        }

        // Use QueryBus to fetch rows for selected ids (handler GetCompaniesForGrid ignores filters, but we can pass ids)
        $query = new \Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompaniesForGrid(['ids' => $companyIds]);
        $companies = $this->getQueryBus()->handle($query);

        if (empty($companies)) {
            $this->addFlash('warning', $this->translate('No se encontraron registros para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
        }

        $fileName = sprintf('rj_multicarrier_companies_seleccion_%s.csv', date('Ymd_His'));

        return $this->createCompanyCsvResponse($companies, $fileName);
    }

    /**
     * Create CSV response from companies data. Accepts either Company entities or plain arrays
     * with keys: id_carrier_company|id, name, shortName|shortname, icon, shops|shopIds.
     *
     * @param iterable|
oanja\Module\RjMulticarrier\Entity\Company[] $companies
     */
    private function createCompanyCsvResponse(iterable $companies, string $fileName): Response
    {
        $headers = ['ID', 'Name', 'ShortName', 'Icon', 'Shops'];

        $csvContent = implode(',', $headers) . "\n";

        foreach ($companies as $company) {
            // Support two shapes: entity or array
            if (is_array($company)) {
                $id = $company['id_carrier_company'] ?? ($company['id'] ?? '');
                $name = $company['name'] ?? '';
                $shortName = $company['shortName'] ?? ($company['shortname'] ?? '');
                $icon = $company['icon'] ?? '';
                $shops = $company['shops'] ?? ($company['shopIds'] ?? '');
                if (is_array($shops)) {
                    $shops = implode(';', $shops);
                }
            } elseif ($company instanceof \Roanja\Module\RjMulticarrier\Entity\Company) {
                $id = $company->getId();
                $name = $company->getName() ?? '';
                $shortName = $company->getShortName() ?? '';
                $icon = $company->getIcon() ?? '';
                $shops = $company->getShopIds();
                $shops = is_array($shops) ? implode(';', $shops) : '';
            } else {
                // Unknown shape, skip
                continue;
            }

            $row = [
                $id,
                '"' . str_replace('"', '""', (string) $name) . '"',
                '"' . str_replace('"', '""', (string) $shortName) . '"',
                '"' . str_replace('"', '""', (string) $icon) . '"',
                '"' . str_replace('"', '""', (string) $shops) . '"',
            ];

            $csvContent .= implode(',', $row) . "\n";
        }

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }

    /**
     * @return array<int>
     */
    private function getBulkCompanyIds(Request $request): array
    {
        $gridId = CompanyGridDefinitionFactory::GRID_ID;
        $columnName = $gridId . '_company_bulk';

        $collected = [];

        $gridPayload = $request->request->get($gridId);
        if (is_array($gridPayload)) {
            if (isset($gridPayload[$columnName]['ids']) && is_array($gridPayload[$columnName]['ids'])) {
                $collected = array_merge($collected, $gridPayload[$columnName]['ids']);
            }

            if (isset($gridPayload[$columnName]) && is_array($gridPayload[$columnName])) {
                $collected = array_merge($collected, $gridPayload[$columnName]);
            }
        }

        $flat = $request->request->get($columnName);
        if (is_array($flat)) {
            $collected = array_merge($collected, $flat);
        }

        $legacy = $request->request->get('ids');
        if (is_array($legacy)) {
            $collected = array_merge($collected, $legacy);
        }

        $collected = array_filter($collected, static function ($value): bool {
            return ctype_digit((string) $value) && (int) $value > 0;
        });

        $collected = array_map(static function ($value): int {
            return (int) $value;
        }, $collected);

        return array_values(array_unique($collected));
    }

    /**
     * @return array<array{id:int,name:string,value:?string}>
     */
    private function getCompanyConfigurationEntries(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        try {
            /** @var CompanyConfigurationView[] $views */
            $views = $this->getQueryBus()->handle(new GetCompanyConfigurationsForCompany($companyId));
        } catch (\Throwable $exception) {
            return [];
        }

        return array_map(static function (CompanyConfigurationView $view): array {
            return [
                'id' => $view->getId(),
                'name' => $view->getName(),
                'value' => $view->getValue(),
            ];
        }, $views);
    }

    /**
     * @return array{id:int,name:string,shortName:string,icon:?string,shops:array<int>}
     */
    private function getCompanyData(int $companyId): array
    {
        /** @var CompanyDetailView|null $view */
        $view = $this->getQueryBus()->handle(new GetCompanyForView($companyId));

        if (!$view instanceof CompanyDetailView) {
            $this->addFlash('error', $this->translate('Compañía no encontrada.'));
            throw $this->createNotFoundException();
        }

        $data = $view->toArray();

        return [
            'id' => (int) ($data['id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'shortName' => (string) ($data['shortName'] ?? ''),
            'icon' => $data['icon'] ?? null,
            'shops' => $data['shops'] ?? [],
        ];
    }

    private function parseConfigurationEntries(Request $request): array
    {
        $payload = $request->request->get('configurations', []);

        if (!is_array($payload)) {
            return [];
        }

        $ids = $payload['id'] ?? [];
        $names = $payload['name'] ?? [];
        $values = $payload['value'] ?? [];

        $max = max([
            is_array($ids) ? count($ids) : 0,
            is_array($names) ? count($names) : 0,
            is_array($values) ? count($values) : 0,
        ]);

        $entries = [];

        for ($index = 0; $index < $max; ++$index) {
            $id = is_array($ids) && array_key_exists($index, $ids) ? (int) $ids[$index] : 0;
            $name = is_array($names) && array_key_exists($index, $names) ? trim((string) $names[$index]) : '';
            $valueRaw = is_array($values) && array_key_exists($index, $values) ? $values[$index] : null;
            $value = ($valueRaw === null || '' === $valueRaw) ? null : (string) $valueRaw;

            if ('' === $name) {
                if ($id > 0) {
                    $entries[] = [
                        'id' => $id,
                        'delete' => true,
                    ];
                }

                continue;
            }

            $entries[] = [
                'id' => $id,
                'name' => $name,
                'value' => $value,
            ];
        }

        return $entries;
    }

    private function syncCompanyConfigurations(int $companyId, array $entries): void
    {
        if ($companyId <= 0) {
            return;
        }

        try {
            /** @var CompanyConfigurationView[] $existingViews */
            $existingViews = $this->getQueryBus()->handle(new GetCompanyConfigurationsForCompany($companyId));
        } catch (\Throwable $exception) {
            return;
        }

        $existingIds = [];
        foreach ($existingViews as $view) {
            $existingIds[$view->getId()] = true;
        }

        $processedIds = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = isset($entry['id']) ? (int) $entry['id'] : 0;
            $delete = !empty($entry['delete']);

            if ($delete) {
                if ($id > 0) {
                    $this->getCommandBus()->handle(new DeleteCompanyConfigurationCommand($id));
                    $processedIds[] = $id;
                }

                continue;
            }

            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';
            if ('' === $name) {
                continue;
            }

            $value = $entry['value'] ?? null;

            $this->getCommandBus()->handle(new UpsertCompanyConfigurationCommand(
                $companyId,
                $name,
                $value,
                $id > 0 ? $id : null
            ));

            if ($id > 0) {
                $processedIds[] = $id;
            }
        }

        $existingIdsList = array_keys($existingIds);
        $idsToDelete = array_diff($existingIdsList, $processedIds);

        foreach ($idsToDelete as $id) {
            $this->getCommandBus()->handle(new DeleteCompanyConfigurationCommand((int) $id));
        }
    }

    private function translate(string $message, array $parameters = []): string
    {
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }

    private function handleIconUpload(UploadedFile $file, Company $company): bool
    {
        // basic validation
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $mime = $file->getMimeType();
        if (null === $mime || 0 !== strpos($mime, 'image/')) {
            return false;
        }

        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ($file->getSize() !== null && $file->getSize() > $maxBytes) {
            return false;
        }

        $targetDir = defined('IMG_ICON_COMPANY_DIR') ? IMG_ICON_COMPANY_DIR : (_PS_MODULE_DIR_ . 'rj_multicarrier/var/icons/');
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return false;
        }

        $extension = $file->guessExtension() ?: 'png';
        $safeName = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(4)), $extension);

        try {
            $file->move($targetDir, $safeName);
        } catch (\Throwable $e) {
            return false;
        }

        // remove previous icon if any
        $this->removeIconFile($company->getIcon());

        // store the filename (not full path)
        $company->setIcon($safeName);

        // Attempt to generate a small thumbnail for grid display
        try {
            $source = $targetDir . $safeName;
            $thumbName = $this->makeThumbName($safeName);
            $thumbPath = $targetDir . $thumbName;
            $this->createImageThumbnail($source, $thumbPath, 120, 48);
        } catch (\Throwable $e) {
            // silently ignore thumbnail generation errors
        }

        return true;
    }

    /**
     * Move uploaded icon file to module storage and create thumbnail. Returns filename on success or null on failure.
     */
    private function moveUploadedIcon(UploadedFile $file): ?string
    {
        // basic validation
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $mime = $file->getMimeType();
        if (null === $mime || 0 !== strpos($mime, 'image/')) {
            return null;
        }

        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ($file->getSize() !== null && $file->getSize() > $maxBytes) {
            return null;
        }

        $targetDir = defined('IMG_ICON_COMPANY_DIR') ? IMG_ICON_COMPANY_DIR : (_PS_MODULE_DIR_ . 'rj_multicarrier/var/icons/');
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return null;
        }

        $extension = $file->guessExtension() ?: 'png';
        $safeName = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(4)), $extension);

        try {
            $file->move($targetDir, $safeName);
        } catch (\Throwable $e) {
            return null;
        }

        // create thumb
        try {
            $source = $targetDir . $safeName;
            $thumbName = $this->makeThumbName($safeName);
            $thumbPath = $targetDir . $thumbName;
            $this->createImageThumbnail($source, $thumbPath, 120, 48);
        } catch (\Throwable $e) {
            // ignore thumbnail errors
        }

        return $safeName;
    }

    private function removeIconFile(?string $fileName): void
    {
        if (empty($fileName)) {
            return;
        }

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

    private function buildIconUrl(?string $fileName): ?string
    {
        if (null === $fileName || '' === $fileName) {
            return null;
        }

        // Publicly accessible module path
        $moduleUri = _MODULE_DIR_ . 'rj_multicarrier/var/icons/';
        $thumb = $this->makeThumbName($fileName);
        // prefer thumb if present
        $serverThumbPath = _PS_MODULE_DIR_ . 'rj_multicarrier/var/icons/' . $thumb;
        if (is_file($serverThumbPath)) {
            return $moduleUri . $thumb;
        }

        return $moduleUri . $fileName;
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

    private function createImageThumbnail(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight): bool
    {
        if (!is_file($sourcePath)) {
            return false;
        }

        $info = getimagesize($sourcePath);
        if (false === $info) {
            return false;
        }

        [$width, $height, $type] = $info;

        // Calculate new size preserving aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newW = (int) max(1, floor($width * $ratio));
        $newH = (int) max(1, floor($height * $ratio));

        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImg = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $srcImg = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $srcImg = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        if (false === $srcImg) {
            return false;
        }

        $dstImg = imagecreatetruecolor($newW, $newH);
        // preserve transparency for PNG/GIF
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagecolortransparent($dstImg, imagecolorallocatealpha($dstImg, 0, 0, 0, 127));
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
        }

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);

        $ok = false;
        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $ok = imagejpeg($dstImg, $targetPath, 85);
                break;
            case 'png':
                $ok = imagepng($dstImg, $targetPath);
                break;
            case 'gif':
                $ok = imagegif($dstImg, $targetPath);
                break;
            default:
                // fallback to png
                $ok = imagepng($dstImg, $targetPath);
                break;
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return (bool) $ok;
    }

    private function extractScopedParameters(Request $request, string $scope): array
    {
        // The grid submits scoped parameters under the grid id (e.g. rj_multicarrier_company[filters][...]).
        // Use get($scope, []) to retrieve the nested array from either query or request.
        $parameters = $request->query->get($scope, []);

        if (!is_array($parameters) || empty($parameters)) {
            $parameters = $request->request->get($scope, []);
        }

        return is_array($parameters) ? $parameters : [];
    }

    /**
     * Return available shops in the format [[ 'id_shop' => int, 'name' => string ], ...]
     * Uses PrestaShop Shop/Context classes when available.
     *
     * @return array<int, array{id_shop:int,name:string}>
     */
    private function getAvailableShops(): array
    {
        // Prefer using the controller context when available (same pattern as ps_linklist LinkBlockController)
        $context = null;
        if (method_exists($this, 'getContext')) {
            // FrameworkBundleAdminController exposes getContext()
            try {
                $context = $this->getContext();
            } catch (\Throwable $e) {
                $context = null;
            }
        }

        // Fallback to legacy Context::getContext() if needed
        if (null === $context && \is_callable(['\\Context', 'getContext'])) {
            $context = \call_user_func(['\\Context', 'getContext']);
        }

        // If multistore feature is not active, return current shop from context when present
        $shopClass = '';
        if (class_exists('\\Shop')) {
            $shopClass = '\\Shop';
        } elseif (class_exists('\\ShopCore')) {
            $shopClass = '\\ShopCore';
        }

        $isFeatureActive = false;
        if ('' !== $shopClass && \is_callable([$shopClass, 'isFeatureActive'])) {
            $isFeatureActive = (bool) \call_user_func([$shopClass, 'isFeatureActive']);
        }

        if (!$isFeatureActive) {
            if (isset($context->shop->id, $context->shop->name)) {
                return [[
                    'id_shop' => (int) $context->shop->id,
                    'name' => (string) $context->shop->name,
                ]];
            }

            return [];
        }

        // Multistore active: return full shops list
        if ('' !== $shopClass && \is_callable([$shopClass, 'getShops'])) {
            $shops = \call_user_func([$shopClass, 'getShops'], false);
            return \is_array($shops) ? $shops : [];
        }

        return [];
    }

    private function getIntParam(Request $request, array $scopedParameters, string $key, int $default): int
    {
        if (isset($scopedParameters[$key])) {
            return (int) $scopedParameters[$key];
        }

        if ($request->query->has($key)) {
            return $request->query->getInt($key, $default);
        }

        if ($request->request->has($key)) {
            return $request->request->getInt($key, $default);
        }

        return $default;
    }

    private function getStringParam(Request $request, array $scopedParameters, string $key, string $default): string
    {
        if (isset($scopedParameters[$key]) && '' !== (string) $scopedParameters[$key]) {
            return (string) $scopedParameters[$key];
        }

        if ($request->query->has($key)) {
            return (string) $request->query->get($key, $default);
        }

        if ($request->request->has($key)) {
            return (string) $request->request->get($key, $default);
        }

        return $default;
    }

    private function getArrayParam(Request $request, array $scopedParameters, string $key, array $default): array
    {
        if (isset($scopedParameters[$key]) && is_array($scopedParameters[$key])) {
            return $scopedParameters[$key];
        }

        $queryValue = $request->query->get($key);
        if (is_array($queryValue)) {
            return $queryValue;
        }

        $requestValue = $request->request->get($key);
        if (is_array($requestValue)) {
            return $requestValue;
        }

        return $default;
    }
}
