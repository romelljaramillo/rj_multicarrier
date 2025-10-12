<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\DeleteConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\ConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;

final class DeleteConfigurationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigurationRepository $ConfigurationRepository
    ) {
    }

    public function handle(DeleteConfigurationCommand $command): void
    {
        $Configuration = $this->ConfigurationRepository->find($command->getConfigurationId());

        if (null === $Configuration) {
            throw ConfigurationNotFoundException::forId($command->getConfigurationId());
        }

        foreach ($Configuration->getShops() as $mapping) {
            $this->entityManager->remove($mapping);
        }

        $this->entityManager->remove($Configuration);
        $this->entityManager->flush();
    }
}
