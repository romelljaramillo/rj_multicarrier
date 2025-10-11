<?php
/**
 * Lightweight bridge controller to keep legacy AJAX integrations alive.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AjaxBridgeController extends FrameworkBundleAdminController
{
    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function handleAction(Request $request): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'message' => 'RJ Multicarrier AJAX bridge ready',
            'timestamp' => time(),
            'echo' => $request->request->all() + $request->query->all(),
        ]);
    }
}
