<?php
/**
 * Admin controller to manage per type-shipment configuration key/value pairs.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Command\DeleteTypeShipmentConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Command\UpsertTypeShipmentConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Exception\TypeShipmentConfigurationException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Exception\TypeShipmentConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query\GetTypeShipmentConfigurationForView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query\GetTypeShipmentConfigurations;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\View\TypeShipmentConfigurationView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\View\TypeShipmentView;
use Roanja\Module\RjMulticarrier\Form\Configuration\CarrierConfigurationType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TypeShipmentConfigurationController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request, int $id): Response
    {
        $typeShipment = $this->getTypeShipmentData($id);
        $typeShipmentId = $typeShipment['id'];

        $configurationId = $request->query->getInt('configId', 0);
        $configurationView = null;

        if ($configurationId > 0) {
            try {
                /** @var TypeShipmentConfigurationView $view */
                $view = $this->getQueryBus()->handle(new GetTypeShipmentConfigurationForView($configurationId));

                if ($view->getTypeShipmentId() === $typeShipmentId) {
                    $configurationView = $view;
                }
            } catch (TypeShipmentConfigurationNotFoundException $exception) {
                $this->addFlash('warning', $this->l('La configuración seleccionada ya no existe.'));
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo cargar la configuración solicitada.'));
            }
        }

        $formData = [
            'name' => $configurationView?->getName(),
            'value' => $configurationView?->getValue(),
        ];

        $formActionParameters = ['id' => $typeShipmentId];
        if ($configurationId > 0) {
            $formActionParameters['configId'] = $configurationId;
        }

        $form = $this->createForm(CarrierConfigurationType::class, $formData, [
            'lock_name' => null !== $configurationView,
            'label_name' => $this->l('Configuración'),
            'label_value' => $this->l('Valor'),
            'action' => $this->generateUrl('admin_rj_multicarrier_type_shipment_configuration', $formActionParameters),
            'method' => Request::METHOD_POST,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->getCommandBus()->handle(new UpsertTypeShipmentConfigurationCommand(
                    $typeShipmentId,
                    (string) ($data['name'] ?? ''),
                    $data['value'] ?? null,
                    $configurationView?->getId()
                ));

                $message = null === $configurationView
                    ? $this->l('Configuración creada correctamente.')
                    : $this->l('Configuración actualizada correctamente.');
                $this->addFlash('success', $message);

                return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_configuration', [
                    'id' => $typeShipmentId,
                ]);
            } catch (TypeShipmentConfigurationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar la configuración.'));
            }
        }

        $configurationViews = $this->getQueryBus()->handle(new GetTypeShipmentConfigurations($typeShipmentId));
        $configurations = array_map(static fn (TypeShipmentConfigurationView $view): array => $view->toArray(), $configurationViews);
        $company = $typeShipment['company'];

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/type_shipment/configuration.html.twig', [
            'typeShipment' => $typeShipment,
            'company' => $company,
            'configurations' => $configurations,
            'configurationForm' => $form->createView(),
            'isEditing' => null !== $configurationView,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteAction(Request $request, int $id, int $configId): RedirectResponse
    {
        $typeShipment = $this->getTypeShipmentData($id);
        $typeShipmentId = $typeShipment['id'];

        // No CSRF token validation to match other native controllers pattern

        try {
            $this->getCommandBus()->handle(new DeleteTypeShipmentConfigurationCommand($configId));
            $this->addFlash('success', $this->l('Configuración eliminada correctamente.'));
        } catch (TypeShipmentConfigurationNotFoundException $exception) {
            $this->addFlash('warning', $this->l('La configuración seleccionada ya no existe.'));
        } catch (TypeShipmentConfigurationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->addFlash('error', $this->l('No se pudo eliminar la configuración.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_configuration', ['id' => $typeShipmentId]);
    }

    private function l(string $message, array $parameters = []): string
    {
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }

    /**
     * @return array{id:int,name:string,businessCode:string,company:array{id:int,name:string,shortName:?string}}
     */
    private function getTypeShipmentData(int $typeShipmentId): array
    {
        /** @var TypeShipmentView|null $view */
        $view = $this->getQueryBus()->handle(new GetTypeShipmentForView($typeShipmentId));

        if (!$view instanceof TypeShipmentView) {
            $this->addFlash('error', $this->l('Tipo de envío no encontrado.'));
            throw $this->createNotFoundException();
        }

        $data = $view->toArray();

        return [
            'id' => (int) ($data['id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'businessCode' => (string) ($data['businessCode'] ?? ''),
            'company' => [
                'id' => (int) ($data['companyId'] ?? 0),
                'name' => (string) ($data['companyName'] ?? ''),
                'shortName' => $data['companyShortName'] ?? null,
            ],
        ];
    }
}
