<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\UpdateConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\ConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\InvalidConfigurationDataException;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Entity\ConfigurationShop;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpdateConfigurationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigurationRepository $ConfigurationRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function handle(UpdateConfigurationCommand $command): Configuration
    {
        $violations = $this->validator->validate($command);
        if (0 !== $violations->count()) {
            throw InvalidConfigurationDataException::fromViolations($violations);
        }

        $Configuration = $this->ConfigurationRepository->find($command->id);
        if (!$Configuration instanceof Configuration) {
            throw ConfigurationNotFoundException::forId($command->id);
        }

        $this->applyCommandData($Configuration, $command);
        $this->syncShopAssociation($Configuration, $command->getNormalizedShopAssociation());

        $this->entityManager->flush();

        return $Configuration;
    }

    private function applyCommandData(Configuration $Configuration, UpdateConfigurationCommand $command): void
    {
        $Configuration
            ->setFirstName($command->firstname)
            ->setLastName($command->lastname)
            ->setCompany($this->sanitize($command->company))
            ->setAdditionalName($this->sanitize($command->additionalname))
            ->setCountryId($command->id_country)
            ->setState($command->state)
            ->setCity($command->city)
            ->setStreet($command->street)
            ->setStreetNumber($command->number)
            ->setPostcode($command->postcode)
            ->setAdditionalAddress($this->sanitize($command->additionaladdress))
            ->setIsBusinessFlag($this->normalizeBoolean($command->isbusiness))
            ->setEmail($this->sanitize($command->email))
            ->setPhone($command->phone)
            ->setVatNumber($this->sanitize($command->vatnumber))
            ->setLabelPrefix($command->RJ_ETIQUETA_TRANSP_PREFIX)
            ->setCashOnDeliveryModule($command->RJ_MODULE_CONTRAREEMBOLSO);
    }

    /**
     * @param int[] $shopIds
     */
    private function syncShopAssociation(Configuration $Configuration, array $shopIds): void
    {
        $entityManager = $this->entityManager;

        $existingIds = [];
        $collection = $Configuration->getShops();

        foreach ($collection as $mapping) {
            $currentShopId = (int) $mapping->getShopId();
            if (in_array($currentShopId, $shopIds, true)) {
                $existingIds[] = $currentShopId;
                continue;
            }

            $collection->removeElement($mapping);
            $entityManager->remove($mapping);
        }

        foreach ($shopIds as $shopId) {
            if (in_array($shopId, $existingIds, true)) {
                continue;
            }

            $mapping = new ConfigurationShop($Configuration, $shopId);
            $entityManager->persist($mapping);

            if (method_exists($collection, 'add')) {
                $collection->add($mapping);
            }
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
