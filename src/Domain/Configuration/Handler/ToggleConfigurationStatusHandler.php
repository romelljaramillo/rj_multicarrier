<?php
/**
 * Handler responsible for toggling Configuration active status.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\ToggleConfigurationStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\ConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;

final class ToggleConfigurationStatusHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigurationRepository $ConfigurationRepository
    ) {
    }

    public function handle(ToggleConfigurationStatusCommand $command): Configuration
    {
        $Configuration = $this->ConfigurationRepository->find($command->getConfigurationId());

        if (!$Configuration instanceof Configuration) {
            throw ConfigurationNotFoundException::forId($command->getConfigurationId());
        }

        $Configuration->setActive(!$Configuration->isActive());
        $this->entityManager->flush();

        return $Configuration;
    }
}
