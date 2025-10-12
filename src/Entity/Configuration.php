<?php
/**
 * Doctrine entity representing ship sender configuration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Roanja\Module\RjMulticarrier\Entity\Traits\TimestampableTrait;

#[ORM\Entity(repositoryClass: \Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository::class)]
#[ORM\Table(name: self::TABLE_NAME)]
class Configuration
{
    use TimestampableTrait;

    public const TABLE_NAME = _DB_PREFIX_ . 'rj_multicarrier_configuration';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_configuration', type: 'integer')]
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

    /**
     * Legacy mapping to shops.
     *
     * @var Collection<int, ConfigurationShop>
     */
    #[ORM\OneToMany(targetEntity: ConfigurationShop::class, mappedBy: 'configuration', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $shops;

    #[ORM\Column(name: 'vatnumber', type: 'string', length: 100, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(name: 'label_prefix', type: 'string', length: 64, nullable: true)]
    private ?string $labelPrefix = null;

    #[ORM\Column(name: 'cod_module', type: 'string', length: 255, nullable: true)]
    private ?string $cashOnDeliveryModule = null;

    #[ORM\Column(name: 'active', type: 'boolean', options: ['default' => true])]
    private bool $active = true;

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
        $this->shops = new ArrayCollection();
    }

    /**
     * @return Collection<int, ConfigurationShop>
     */
    public function getShops(): Collection
    {
        return $this->shops;
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

    public function getLabelPrefix(): ?string
    {
        return $this->labelPrefix;
    }

    public function setLabelPrefix(?string $labelPrefix): self
    {
        if (null === $labelPrefix) {
            $this->labelPrefix = null;
        } else {
            $clean = trim($labelPrefix);
            $this->labelPrefix = '' === $clean ? null : $clean;
        }

        return $this;
    }

    public function getCashOnDeliveryModule(): ?string
    {
        return $this->cashOnDeliveryModule;
    }

    public function setCashOnDeliveryModule(?string $module): self
    {
        if (null === $module) {
            $this->cashOnDeliveryModule = null;
        } else {
            $clean = trim($module);
            $this->cashOnDeliveryModule = '' === $clean ? null : $clean;
        }

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}
