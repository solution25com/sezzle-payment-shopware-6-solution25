<?php

declare(strict_types=1);

namespace Sezzle\DataAbstractionLayer\Entity\SezzleCustomer;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SezzleCustomerEntity extends Entity
{
    use EntityIdTrait;

    protected string $sezzleCustomerUuid;
    protected ?string $merchantUuid = null;
    protected ?string $firstName = null;
    protected ?string $lastName = null;
    protected ?string $email = null;
    protected ?string $phone = null;
    protected ?\DateTimeInterface $dateOfBirth = null;
    protected ?array $billingAddress = null;
    protected ?array $shippingAddress = null;
    protected bool $isTokenized = false;
    protected ?\DateTimeInterface $tokenizedAt = null;
    protected ?string $salesChannelId = null;
    protected ?string $shopwareCustomerId = null;
    protected ?array $rawData = null;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?CustomerEntity $shopwareCustomer = null;
    public function getSezzleCustomerUuid(): string
    {
        return $this->sezzleCustomerUuid;
    }
    public function setSezzleCustomerUuid(string $sezzleCustomerUuid): void
    {
        $this->sezzleCustomerUuid = $sezzleCustomerUuid;
    }
    public function getMerchantUuid(): ?string
    {
        return $this->merchantUuid;
    }
    public function setMerchantUuid(?string $merchantUuid): void
    {
        $this->merchantUuid = $merchantUuid;
    }
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }
    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }
    public function getLastName(): ?string
    {
        return $this->lastName;
    }
    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }
    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }
    public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): void
    {
        $this->dateOfBirth = $dateOfBirth;
    }
    public function getBillingAddress(): ?array
    {
        return $this->billingAddress;
    }
    public function setBillingAddress(?array $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }
    public function getShippingAddress(): ?array
    {
        return $this->shippingAddress;
    }
    public function setShippingAddress(?array $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }
    public function isTokenized(): bool
    {
        return $this->isTokenized;
    }
    public function getIsTokenized(): bool
    {
        return $this->isTokenized;
    }
    public function setIsTokenized(bool $isTokenized): void
    {
        $this->isTokenized = $isTokenized;
    }
    public function getTokenizedAt(): ?\DateTimeInterface
    {
        return $this->tokenizedAt;
    }
    public function setTokenizedAt(?\DateTimeInterface $tokenizedAt): void
    {
        $this->tokenizedAt = $tokenizedAt;
    }
    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }
    public function getShopwareCustomerId(): ?string
    {
        return $this->shopwareCustomerId;
    }
    public function setShopwareCustomerId(?string $shopwareCustomerId): void
    {
        $this->shopwareCustomerId = $shopwareCustomerId;
    }
    public function getRawData(): ?array
    {
        return $this->rawData;
    }
    public function setRawData(?array $rawData): void
    {
        $this->rawData = $rawData;
    }
    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }
    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
    public function getShopwareCustomer(): ?CustomerEntity
    {
        return $this->shopwareCustomer;
    }
    public function setShopwareCustomer(?CustomerEntity $shopwareCustomer): void
    {
        $this->shopwareCustomer = $shopwareCustomer;
    }
}
