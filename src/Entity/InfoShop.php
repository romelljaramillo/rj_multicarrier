<?php
/**
 * Doctrine entity representing shipper (shop) metadata.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\InfoShopRepository::class)]
#[ORM\Table(name: self::TABLE_NAME)]
class InfoShop
{
    use TimestampableTrait;

    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_infoshop';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_infoshop', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'firstname', type: 'string', length: 100)]
    private string $firstName;

    #[ORM\Column(name: 'lastname', type: 'string', length: 100)]
    private string $lastName;

    #[ORM\Column(name: 'company', type: 'string', length: 100, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(name: 'additionalname', type: 'string', length: 100, nullable: true)]
    private ?string $additionalName = null;

    #[ORM\Column(name: 'id_country', type: 'integer')]
    private int $countryId;

    #[ORM\Column(name: 'state', type: 'string', length: 255)]
    private string $state;

    #[ORM\Column(name: 'city', type: 'string', length: 255)]
    private string $city;

    #[ORM\Column(name: 'street', type: 'string', length: 100)]
    private string $street;

    #[ORM\Column(name: 'number', type: 'string', length: 100)]
    private string $streetNumber;

    #[ORM\Column(name: 'postcode', type: 'string', length: 100)]
    private string $postcode;

    #[ORM\Column(name: 'additionaladdress', type: 'string', length: 100, nullable: true)]
    private ?string $additionalAddress = null;

    #[ORM\Column(name: 'isbusiness', type: 'string', length: 100, nullable: true)]
    private ?string $isBusiness = null;

    #[ORM\Column(name: 'email', type: 'string', length: 100, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'phone', type: 'string', length: 100)]
    private string $phone;

    #[ORM\Column(name: 'vatnumber', type: 'string', length: 100, nullable: true)]
    private ?string $vatNumber = null;

    public function __construct(
        string $firstName,
        string $lastName,
        int $countryId,
        string $state,
        string $city,
        string $street,
        string $streetNumber,
        string $postcode,
        string $phone
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->countryId = $countryId;
        $this->state = $state;
        $this->city = $city;
        $this->street = $street;
        $this->streetNumber = $streetNumber;
        $this->postcode = $postcode;
        $this->phone = $phone;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getAdditionalName(): ?string
    {
        return $this->additionalName;
    }

    public function setAdditionalName(?string $additionalName): self
    {
        $this->additionalName = $additionalName;

        return $this;
    }

    public function getCountryId(): int
    {
        return $this->countryId;
    }

    public function setCountryId(int $countryId): self
    {
        $this->countryId = $countryId;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): self
    {
        $this->street = $street;

        return $this;
    }

    public function getStreetNumber(): string
    {
        return $this->streetNumber;
    }

    public function setStreetNumber(string $streetNumber): self
    {
        $this->streetNumber = $streetNumber;

        return $this;
    }

    public function getPostcode(): string
    {
        return $this->postcode;
    }

    public function setPostcode(string $postcode): self
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function getAdditionalAddress(): ?string
    {
        return $this->additionalAddress;
    }

    public function setAdditionalAddress(?string $additionalAddress): self
    {
        $this->additionalAddress = $additionalAddress;

        return $this;
    }

    public function getIsBusinessFlag(): ?string
    {
        return $this->isBusiness;
    }

    public function setIsBusinessFlag(?string $isBusiness): self
    {
        $this->isBusiness = $isBusiness;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): self
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }
}
