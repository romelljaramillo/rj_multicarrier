<?php
/**
 * Handles front-office widget rendering for carrier selection hooks.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Presenter;

use Twig\Environment;

final class WidgetPresenter
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function render(string $hookName, array $configuration): string
    {
    return $this->twig->render('@Modules/rj_multicarrier/views/templates/front/widget.html.twig', [
            'hook' => $hookName,
            'configuration' => $configuration,
        ]);
    }

    public function getVariables(string $hookName, array $configuration): array
    {
        return [
            'hook' => $hookName,
            'configuration' => $configuration,
        ];
    }
}
