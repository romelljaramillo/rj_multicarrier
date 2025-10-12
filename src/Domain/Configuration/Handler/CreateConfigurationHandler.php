<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\CreateConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\InvalidConfigurationDataException;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Entity\ConfigurationShop;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateConfigurationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function handle(CreateConfigurationCommand $command): Configuration
    {
        $violations = $this->validator->validate($command);
        if (0 !== $violations->count()) {
            throw InvalidConfigurationDataException::fromViolations($violations);
        }

        $Configuration = new Configuration(
            $command->firstname,
            $command->lastname,
            $command->id_country,
            $command->state,
            $command->city,
            $command->street,
            $command->number,
            $command->postcode,
            $command->phone
        );

        $Configuration
            ->setCompany($this->sanitize($command->company))
            ->setAdditionalName($this->sanitize($command->additionalname))
            ->setAdditionalAddress($this->sanitize($command->additionaladdress))
            ->setIsBusinessFlag($this->normalizeBoolean($command->isbusiness))
            ->setEmail($this->sanitize($command->email))
            ->setVatNumber($this->sanitize($command->vatnumber))
            ->setLabelPrefix($command->RJ_ETIQUETA_TRANSP_PREFIX)
            ->setCashOnDeliveryModule($command->RJ_MODULE_CONTRAREEMBOLSO)
            ->setActive(true);

        $this->syncShopAssociation($Configuration, $command->getNormalizedShopAssociation());

        $this->entityManager->persist($Configuration);
        $this->entityManager->flush();

        return $Configuration;
    }

    /**
     * @param int[] $shopIds
     */
    private function syncShopAssociation(Configuration $Configuration, array $shopIds): void
    {
        foreach ($shopIds as $shopId) {
            $mapping = new ConfigurationShop($Configuration, $shopId);
            $this->entityManager->persist($mapping);
            $Configuration->getShops()->add($mapping);
        }
    }

    private function sanitize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function normalizeBoolean(?bool $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value ? '1' : '0';
    }

}
