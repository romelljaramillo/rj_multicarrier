<?php
/**
 * Admin controller to manage per-carrier configuration key/value pairs.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Command\DeleteCarrierConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Command\UpsertCarrierConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Exception\CarrierConfigurationException;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Exception\CarrierConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query\GetCarrierConfigurationForView;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query\GetCarrierConfigurationsForCarrier;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\View\CarrierConfigurationView;
use Roanja\Module\RjMulticarrier\Domain\Carrier\Query\GetCarrierForView;
use Roanja\Module\RjMulticarrier\Domain\Carrier\View\CarrierDetailView;
use Roanja\Module\RjMulticarrier\Form\Configuration\CarrierConfigurationType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CarrierConfigurationController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request, int $id): Response
    {
        $carrier = $this->getCarrierData($id);
        $carrierId = $carrier['id'];

        $configurationId = $request->query->getInt('configId', 0);
        $configurationView = null;

        if ($configurationId > 0) {
            try {
                /** @var CarrierConfigurationView $view */
                $view = $this->getQueryBus()->handle(new GetCarrierConfigurationForView($configurationId));

                if ($view->getCarrierId() === $carrierId) {
                    $configurationView = $view;
                }
            } catch (CarrierConfigurationNotFoundException $exception) {
                $this->addFlash('warning', $this->l('La configuración solicitada ya no existe.'));
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo cargar la configuración seleccionada.'));
            }
        }

        $formData = [
            'name' => $configurationView?->getName(),
            'value' => $configurationView?->getValue(),
        ];

    $formActionParameters = ['id' => $carrierId];
        if ($configurationId > 0) {
            $formActionParameters['configId'] = $configurationId;
        }

        $form = $this->createForm(CarrierConfigurationType::class, $formData, [
            'lock_name' => null !== $configurationView,
            'label_name' => $this->l('Configuración'),
            'label_value' => $this->l('Valor'),
            'help_value' => $this->l('Puedes usar variables específicas del transportista si están disponibles.'),
            'action' => $this->generateUrl('admin_rj_multicarrier_carriers_configuration', $formActionParameters),
            'method' => Request::METHOD_POST,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->getCommandBus()->handle(new UpsertCarrierConfigurationCommand(
                    $carrierId,
                    (string) ($data['name'] ?? ''),
                    $data['value'] ?? null,
                    $configurationView?->getId()
                ));

                $message = null === $configurationView
                    ? $this->l('Configuración creada correctamente.')
                    : $this->l('Configuración actualizada correctamente.');
                $this->addFlash('success', $message);

                return $this->redirectToRoute('admin_rj_multicarrier_carriers_configuration', [
                    'id' => $carrierId,
                ]);
            } catch (CarrierConfigurationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar la configuración.'));
            }
        }

        $configurationViews = $this->getQueryBus()->handle(new GetCarrierConfigurationsForCarrier($carrierId));
        $configurations = array_map(static fn (CarrierConfigurationView $view): array => $view->toArray(), $configurationViews);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/carrier/configuration.html.twig', [
            'carrier' => $carrier,
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
        $carrier = $this->getCarrierData($id);
        $carrierId = $carrier['id'];

        // No CSRF token validation to match other native controllers pattern

        try {
            $this->getCommandBus()->handle(new DeleteCarrierConfigurationCommand($configId));
            $this->addFlash('success', $this->l('Configuración eliminada correctamente.'));
        } catch (CarrierConfigurationNotFoundException $exception) {
            $this->addFlash('warning', $this->l('La configuración seleccionada ya no existe.'));
        } catch (CarrierConfigurationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->addFlash('error', $this->l('No se pudo eliminar la configuración.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_carriers_configuration', ['id' => $carrierId]);
    }

    private function l(string $message, array $parameters = []): string
    {
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }

    /**
     * @return array{id:int,name:string,shortName:?string}
     */
    private function getCarrierData(int $carrierId): array
    {
        /** @var CarrierDetailView|null $view */
        $view = $this->getQueryBus()->handle(new GetCarrierForView($carrierId));

        if (!$view instanceof CarrierDetailView) {
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
