<?php
/**
 * Admin controller to manage per-company carrier configuration key/value pairs.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command\DeleteCompanyConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command\UpsertCompanyConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Exception\CompanyConfigurationException;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Exception\CompanyConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query\GetCompanyConfigurationForView;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query\GetCompanyConfigurationsForCompany;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\View\CompanyConfigurationView;
use Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompanyForView;
use Roanja\Module\RjMulticarrier\Domain\Company\View\CompanyDetailView;
use Roanja\Module\RjMulticarrier\Form\Configuration\CarrierConfigurationType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompanyConfigurationController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request, int $id): Response
    {
        $company = $this->getCompanyData($id);
        $companyId = $company['id'];

        $configurationId = $request->query->getInt('configId', 0);
        $configurationView = null;

        if ($configurationId > 0) {
            try {
                /** @var CompanyConfigurationView $view */
                $view = $this->getQueryBus()->handle(new GetCompanyConfigurationForView($configurationId));

                if ($view->getCompanyId() === $companyId) {
                    $configurationView = $view;
                }
            } catch (CompanyConfigurationNotFoundException $exception) {
                $this->addFlash('warning', $this->l('La configuración solicitada ya no existe.'));
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo cargar la configuración seleccionada.'));
            }
        }

        $formData = [
            'name' => $configurationView?->getName(),
            'value' => $configurationView?->getValue(),
        ];

        $form = $this->createForm(CarrierConfigurationType::class, $formData, [
            'lock_name' => null !== $configurationView,
            'label_name' => $this->l('Configuración'),
            'label_value' => $this->l('Valor'),
            'help_value' => $this->l('Puedes usar variables específicas del transportista si están disponibles.'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->getCommandBus()->handle(new UpsertCompanyConfigurationCommand(
                    $companyId,
                    (string) ($data['name'] ?? ''),
                    $data['value'] ?? null,
                    $configurationView?->getId()
                ));

                $message = null === $configurationView
                    ? $this->l('Configuración creada correctamente.')
                    : $this->l('Configuración actualizada correctamente.');
                $this->addFlash('success', $message);

                return $this->redirectToRoute('admin_rj_multicarrier_companies_configuration', [
                    'id' => $companyId,
                ]);
            } catch (CompanyConfigurationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar la configuración.'));
            }
        }

        $configurationViews = $this->getQueryBus()->handle(new GetCompanyConfigurationsForCompany($companyId));
        $configurations = array_map(static fn (CompanyConfigurationView $view): array => $view->toArray(), $configurationViews);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/company/configuration.html.twig', [
            'company' => $company,
            'configurations' => $configurations,
            'configurationForm' => $form->createView(),
            'isEditing' => null !== $configurationView,
            'deleteToken' => $this->generateCsrfToken('delete_company_configuration_' . $companyId),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteAction(Request $request, int $id, int $configId): RedirectResponse
    {
        $company = $this->getCompanyData($id);
        $companyId = $company['id'];

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_company_configuration_' . $companyId, $token)) {
            $this->addFlash('error', $this->l('Token CSRF inválido.'));

            return $this->redirectToRoute('admin_rj_multicarrier_companies_configuration', ['id' => $companyId]);
        }

        try {
            $this->getCommandBus()->handle(new DeleteCompanyConfigurationCommand($configId));
            $this->addFlash('success', $this->l('Configuración eliminada correctamente.'));
        } catch (CompanyConfigurationNotFoundException $exception) {
            $this->addFlash('warning', $this->l('La configuración seleccionada ya no existe.'));
        } catch (CompanyConfigurationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->addFlash('error', $this->l('No se pudo eliminar la configuración.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_companies_configuration', ['id' => $companyId]);
    }

    private function l(string $message, array $parameters = []): string
    {
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }

    private function generateCsrfToken(string $identifier): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken($identifier)->getValue();
    }

    /**
     * @return array{id:int,name:string,shortName:?string}
     */
    private function getCompanyData(int $companyId): array
    {
        /** @var CompanyDetailView|null $view */
        $view = $this->getQueryBus()->handle(new GetCompanyForView($companyId));

        if (!$view instanceof CompanyView) {
            throw $this->createNotFoundException();
        }

        $data = $view->toArray();

        return [
            'id' => (int) ($data['id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'shortName' => $data['shortName'] ?? null,
        ];
    }
}
