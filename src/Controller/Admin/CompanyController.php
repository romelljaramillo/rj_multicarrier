<?php
/**
 * Symfony controller for managing carrier companies.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Grid\Company\CompanyGridFactory;
use Roanja\Module\RjMulticarrier\Grid\Company\CompanyFilters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompanyForView;


final class CompanyController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly EntityManagerInterface $em,
        private readonly CompanyGridFactory $gridFactory
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $filters = $this->buildFilters($request);
        $grid = $this->gridFactory->getGrid($filters);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/company/index.html.twig', [
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
        if ($request->isMethod('POST')) {
            $name = (string) $request->request->get('name', '');
            $shortName = (string) $request->request->get('shortName', '');

            if ('' === $name || '' === $shortName) {
                $this->addFlash('error', $this->translate('Nombre y shortName son obligatorios.'));

                return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
            }

            $company = new Company($name, $shortName);

            // handle uploaded icon
            $iconFile = $request->files->get('icon');
            if ($iconFile instanceof UploadedFile && $iconFile->isValid()) {
                $moved = $this->handleIconUpload($iconFile, $company);
                if (false === $moved) {
                    $this->addFlash('warning', $this->translate('El icon no pudo subirse.'));
                }
            }

            $this->em->persist($company);
            $this->em->flush();

            $this->addFlash('success', $this->translate('Compañía creada correctamente.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/company/form.html.twig', [
            'company' => null,
            'action' => 'create',
            'iconUrl' => null,
        ]);
    }

    public function editAction(Request $request, int $id): Response
    {
        $company = $this->companyRepository->find($id);

        if (null === $company) {
            $this->addFlash('error', $this->translate('Compañía no encontrada.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
        }

        if ($request->isMethod('POST')) {
            $name = (string) $request->request->get('name', $company->getName());
            $shortName = (string) $request->request->get('shortName', $company->getShortName());

            $company->setName($name);
            $company->setShortName($shortName);

            // handle uploaded icon (replace existing)
            $iconFile = $request->files->get('icon');
            if ($iconFile instanceof UploadedFile && $iconFile->isValid()) {
                $moved = $this->handleIconUpload($iconFile, $company);
                if (false === $moved) {
                    $this->addFlash('warning', $this->translate('El icon no pudo subirse.'));
                }
            }

            $this->em->flush();

            $this->addFlash('success', $this->translate('Compañía actualizada correctamente.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/company/form.html.twig', [
            'company' => $company,
            'action' => 'edit',
            'iconUrl' => $this->buildIconUrl($company->getIcon()),
        ]);
    }

    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        $token = (string) $request->request->get('_token');
        $this->validateCsrfToken('delete_company_' . $id, $token);

        try {
            $company = $this->companyRepository->find($id);

            if (null === $company) {
                $this->addFlash('warning', $this->translate('Compañía no encontrada.'));

                return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
            }

            $this->em->remove($company);
            // delete icon file if present
            $this->removeIconFile($company->getIcon());
            $this->em->flush();

            $this->addFlash('success', $this->translate('Compañía eliminada correctamente.'));
        } catch (Throwable $e) {
            $this->addFlash('error', $this->translate('No se pudo eliminar la compañía: %error%', ['%error%' => $e->getMessage()]));
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
            foreach ($companyIds as $id) {
                $company = $this->companyRepository->find($id);
                if ($company instanceof Company) {
                    $this->removeIconFile($company->getIcon());
                    $this->em->remove($company);
                }
            }

            $this->em->flush();
            $this->addFlash('success', $this->translate('%count% registros eliminados.', ['%count%' => count($companyIds)]));
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
        $companies = $this->companyRepository->findAllOrdered();

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

        $companies = [];
        foreach ($companyIds as $id) {
            $company = $this->companyRepository->find($id);
            if (null !== $company) {
                $companies[] = $company;
            }
        }

        if (empty($companies)) {
            $this->addFlash('warning', $this->translate('No se encontraron registros para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_index');
        }

        $fileName = sprintf('rj_multicarrier_companies_seleccion_%s.csv', date('Ymd_His'));

        return $this->createCompanyCsvResponse($companies, $fileName);
    }

    /**
     * @param Company[] $companies
     */
    private function createCompanyCsvResponse(array $companies, string $fileName): Response
    {
        $headers = ['ID', 'Name', 'ShortName', 'Icon', 'Shops'];

        $csvContent = implode(',', $headers) . "\n";

        foreach ($companies as $company) {
            $shops = $company->getShopIds();
            $shopList = is_array($shops) ? implode(';', $shops) : '';

            $row = [
                $company->getId(),
                '"' . str_replace('"', '""', $company->getName() ?? '') . '"',
                '"' . str_replace('"', '""', $company->getShortName() ?? '') . '"',
                '"' . str_replace('"', '""', $company->getIcon() ?? '') . '"',
                '"' . str_replace('"', '""', $shopList) . '"',
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
        $collected = [];

        $payload = $request->request->all();
        foreach ($payload as $value) {
            if (is_array($value) && isset($value['ids']) && is_array($value['ids'])) {
                $collected = array_merge($collected, $value['ids']);
            }
        }

        $legacy = $request->request->get('ids');
        if (is_array($legacy)) {
            $collected = array_merge($collected, $legacy);
        }

        $collected = array_filter($collected, static fn($v) => ctype_digit((string) $v) && (int) $v > 0);
        return array_map(static fn($v) => (int) $v, array_values(array_unique($collected)));
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

        return true;
    }

    private function removeIconFile(?string $fileName): void
    {
        if (empty($fileName)) {
            return;
        }

        $filePath = (defined('IMG_ICON_COMPANY_DIR') ? IMG_ICON_COMPANY_DIR : (_PS_MODULE_DIR_ . 'rj_multicarrier/var/icons/')) . $fileName;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    private function buildIconUrl(?string $fileName): ?string
    {
        if (null === $fileName || '' === $fileName) {
            return null;
        }

        // Publicly accessible module path
        $moduleUri = _MODULE_DIR_ . 'rj_multicarrier/var/icons/';
        return $moduleUri . $fileName;
    }

    private function extractScopedParameters(Request $request, string $scope): array
    {
        $parameters = $request->query->all($scope);

        if (!is_array($parameters) || empty($parameters)) {
            $parameters = $request->request->all($scope);
        }

        return is_array($parameters) ? $parameters : [];
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

    private function validateCsrfToken(string $id, string $token): void
    {
        if (! $this->isCsrfTokenValid($id, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
