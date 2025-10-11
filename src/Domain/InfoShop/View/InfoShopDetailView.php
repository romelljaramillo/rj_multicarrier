<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\View;

final class InfoShopDetailView
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_infoshop' => $this->id,
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
            'shops' => $this->shops,
            'shop_association' => $this->shops,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'active' => $this->active,
        ];
    }
}
