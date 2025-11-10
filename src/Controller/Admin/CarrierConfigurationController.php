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
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query\GetCarrierConfigurationsForCarrier;
use Roanja\Module\RjMulticarrier\Domain\Carrier\Query\GetCarrierForView;
use Roanja\Module\RjMulticarrier\Domain\Carrier\View\CarrierDetailView;
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

        if ($request->isMethod(Request::METHOD_POST)) {
            $token = (string) $request->request->get('_token', '');

            if (!$this->isCsrfTokenValid('carrier_configurations_' . $carrierId, $token)) {
                $this->addFlash('error', $this->l('La sesión ha caducado, vuelve a intentarlo.'));
            } else {
                $this->processConfigurationSubmission($carrierId, $request);
            }

            return $this->redirectToRoute('admin_rj_multicarrier_carriers_configuration', ['id' => $carrierId]);
        }

        $configurationViews = $this->getQueryBus()->handle(new GetCarrierConfigurationsForCarrier($carrierId));
        $defaultConfigurations = [];
        $extraConfigurations = [];

        foreach ($configurationViews as $view) {
            $entry = $view->toArray();

            if ($view->isRequired()) {
                $defaultConfigurations[] = $entry;
                continue;
            }

            $extraConfigurations[] = $entry;
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/carrier/configuration.html.twig', [
            'carrier' => $carrier,
            'defaultConfigurations' => $defaultConfigurations,
            'extraConfigurations' => $extraConfigurations,
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

    private function processConfigurationSubmission(int $carrierId, Request $request): void
    {
        $created = 0;
        $updated = 0;
        $deleted = 0;

        $defaults = $request->request->get('defaults', []);
        if (!is_array($defaults)) {
            $defaults = [];
        }

        foreach ($defaults as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $value = $this->normaliseValue($entry['value'] ?? null);
            $id = isset($entry['id']) ? (int) $entry['id'] : null;

            try {
                $this->getCommandBus()->handle(new UpsertCarrierConfigurationCommand(
                    $carrierId,
                    $name,
                    $value,
                    $id && $id > 0 ? $id : null
                ));

                ++$updated;
            } catch (CarrierConfigurationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar la configuración por defecto "%name%".', ['%name%' => $name]));
            }
        }

        $extras = $request->request->get('extras', []);
        if (!is_array($extras)) {
            $extras = [];
        }

        foreach ($extras as $entry) {
            $id = isset($entry['id']) ? (int) $entry['id'] : 0;
            $removeFlag = (string) ($entry['_delete'] ?? '0');
            $markedForRemoval = in_array($removeFlag, ['1', 'true', 'on'], true);
            $name = trim((string) ($entry['name'] ?? ''));
            $value = $this->normaliseValue($entry['value'] ?? null);

            if ($markedForRemoval) {
                if ($id > 0) {
                    try {
                        $this->getCommandBus()->handle(new DeleteCarrierConfigurationCommand($id));
                        ++$deleted;
                    } catch (CarrierConfigurationNotFoundException) {
                        ++$deleted;
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $this->l('No se pudo eliminar la configuración "%name%".', ['%name%' => $name ?: (string) $id]));
                    }
                }

                continue;
            }

            if ('' === $name) {
                continue;
            }

            try {
                $this->getCommandBus()->handle(new UpsertCarrierConfigurationCommand(
                    $carrierId,
                    $name,
                    $value,
                    $id > 0 ? $id : null
                ));

                if ($id > 0) {
                    ++$updated;
                } else {
                    ++$created;
                }
            } catch (CarrierConfigurationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar la configuración "%name%".', ['%name%' => $name]));
            }
        }

        if (0 === ($created + $updated + $deleted)) {
            $this->addFlash('info', $this->l('No se detectaron cambios en la configuración.'));

            return;
        }

        $messages = [];
        if ($updated > 0) {
            $messages[] = $this->l('%count% configuraciones actualizadas.', ['%count%' => $updated]);
        }
        if ($created > 0) {
            $messages[] = $this->l('%count% configuraciones creadas.', ['%count%' => $created]);
        }
        if ($deleted > 0) {
            $messages[] = $this->l('%count% configuraciones eliminadas.', ['%count%' => $deleted]);
        }

        $this->addFlash('success', implode(' ', $messages));
    }

    private function normaliseValue($value): ?string
    {
        if (null === $value) {
            return null;
        }

        $stringValue = (string) $value;

        return '' === trim($stringValue) ? null : $stringValue;
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
