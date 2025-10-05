<?php
/**
 * Dynamically registers the module Doctrine attribute mappings with the default driver chain.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Doctrine;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DoctrineMappingConfigurator implements EventSubscriberInterface
{
    private bool $registered = false;

    public function __construct(
        private readonly MappingDriverChain $driverChain,
        private readonly AttributeDriver $attributeDriver
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            ConsoleEvents::COMMAND => ['onConsoleCommand', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (method_exists($event, 'isMainRequest')) {
            if (!\call_user_func([$event, 'isMainRequest'])) {
                return;
            }
        } elseif (method_exists($event, 'isMasterRequest') && !\call_user_func([$event, 'isMasterRequest'])) {
            return;
        }

        $this->registerDriver();
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->registerDriver();
    }

    public function registerDriver(): void
    {
        if ($this->registered) {
            return;
        }

        $this->driverChain->addDriver($this->attributeDriver, 'Roanja\\Module\\RjMulticarrier\\Entity');
        $this->registered = true;
    }
}
