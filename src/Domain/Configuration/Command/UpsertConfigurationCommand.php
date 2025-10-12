<?php
/**
 * Command responsible for creating or updating shop sender information.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Command;

/**
 * @psalm-immutable
 */
final class UpsertConfigurationCommand
{
    /**
     * @param int[] $shopAssociation
     */
    public function __construct(
        private readonly ?int $ConfigurationId,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly ?string $company,
        private readonly ?string $additionalName,
        private readonly int $countryId,
        private readonly string $state,
        private readonly string $city,
        private readonly string $street,
        private readonly string $streetNumber,
        private readonly string $postcode,
        private readonly ?string $additionalAddress,
        private readonly ?bool $isBusiness,
        private readonly ?string $email,
        private readonly string $phone,
        private readonly ?string $vatNumber,
        private readonly ?string $labelPrefix,
        private readonly ?string $cashOnDeliveryModule,
        private readonly array $shopAssociation
    ) {
    }

    public function getConfigurationId(): ?int
    {
        return $this->ConfigurationId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getAdditionalName(): ?string
    {
        return $this->additionalName;
    }

    public function getCountryId(): int
    {
        return $this->countryId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getStreetNumber(): string
    {
        return $this->streetNumber;
    }

    public function getPostcode(): string
    {
        return $this->postcode;
    }

    public function getAdditionalAddress(): ?string
    {
        return $this->additionalAddress;
    }

    public function isBusiness(): ?bool
    {
        return $this->isBusiness;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function getLabelPrefix(): ?string
    {
        return $this->labelPrefix;
    }

    public function getCashOnDeliveryModule(): ?string
    {
        return $this->cashOnDeliveryModule;
    }

    /**
     * @return int[]
     */
    public function getShopAssociation(): array
    {
        return $this->shopAssociation;
    }
}
