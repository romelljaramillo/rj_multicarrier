<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\View;

final class ConfigurationDetailView
{
    /**
     * @param array<int> $shops
     */
    public function __construct(
        private readonly int $id,
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
        private readonly array $shops,
        private readonly ?string $createdAt,
        private readonly ?string $updatedAt,
        private readonly bool $active
    ) {
    }

    public function getId(): int
    {
        return $this->id;
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
    public function getShops(): array
    {
        return $this->shops;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_configuration' => $this->id,
            'firstname' => $this->firstName,
            'lastname' => $this->lastName,
            'company' => $this->company,
            'additionalname' => $this->additionalName,
            'id_country' => $this->countryId,
            'state' => $this->state,
            'city' => $this->city,
            'street' => $this->street,
            'number' => $this->streetNumber,
            'postcode' => $this->postcode,
            'additionaladdress' => $this->additionalAddress,
            'isbusiness' => $this->isBusiness,
            'email' => $this->email,
            'phone' => $this->phone,
            'vatnumber' => $this->vatNumber,
            'RJ_ETIQUETA_TRANSP_PREFIX' => $this->labelPrefix,
            'RJ_MODULE_CONTRAREEMBOLSO' => $this->cashOnDeliveryModule,
            'shops' => $this->shops,
            'shop_association' => $this->shops,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'active' => $this->active,
        ];
    }
}
