<?php
declare(strict_types=1);
namespace Sezzle\Services;
use Sezzle\DataAbstractionLayer\Entity\SezzleCustomer\SezzleCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
class SezzleCustomerTokenizationService
{
    public function __construct(
        private readonly EntityRepository $sezzleCustomerRepository,
        private readonly SezzleClientService $sezzleClientService
    ) {
    }
    public function tokenizeCustomerFromOrder(OrderEntity $order, array $sezzleOrderData, string $salesChannelId, Context $context): ?SezzleCustomerEntity
    {
        $orderCustomer = $order->getOrderCustomer();
        $customer = $orderCustomer?->getCustomer();
        if (!$customer) {
            return null;
        }
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress() ?? $billingAddress;
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shopwareCustomerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $existingTokenized = $this->sezzleCustomerRepository->search($criteria, $context)->first();
        if ($existingTokenized && $existingTokenized->isTokenized()) {
            return $existingTokenized;
        }
        $sezzleCustomerUuid = $sezzleOrderData['sezzleCustomerUuid'] ?? null;
        $customerTokenized = $sezzleOrderData['sezzleCustomerTokenized'] ?? false;
        if (!$sezzleCustomerUuid || !$customerTokenized) {
            return null;
        }
        try {
            $sezzleCustomerResponse = $this->sezzleClientService->getCustomer($sezzleCustomerUuid, $salesChannelId);
            if (empty($sezzleCustomerResponse['uuid'])) {
                return null;
            }
            $billingAddressData = [
                'street' => $billingAddress->getStreet(),
                'street2' => '',
                'city' => $billingAddress->getCity(),
                'state' => $billingAddress->getCountryState()?->getShortCode() ?? '',
                'postal_code' => $billingAddress->getZipCode(),
                'country_code' => $billingAddress->getCountry()?->getIso() ?? 'US',
            ];
            $customerEntity = [
                'sezzleCustomerUuid' => $sezzleCustomerResponse['uuid'],
                'merchantUuid' => $sezzleCustomerResponse['merchant_uuid'] ?? null,
                'firstName' => $billingAddress->getFirstName(),
                'lastName' => $billingAddress->getLastName(),
                'email' => $orderCustomer->getEmail(),
                'phone' => $billingAddress->getPhoneNumber(),
                'dateOfBirth' => $customer->getBirthday(),
                'billingAddress' => $billingAddressData,
                'shippingAddress' => [
                    'street' => $shippingAddress->getStreet(),
                    'street2' => '',
                    'city' => $shippingAddress->getCity(),
                    'state' => $shippingAddress->getCountryState()?->getShortCode() ?? '',
                    'postal_code' => $shippingAddress->getZipCode(),
                    'country_code' => $shippingAddress->getCountry()?->getIso() ?? 'US',
                ],
                'isTokenized' => true,
                'tokenizedAt' => new \DateTime(),
                'salesChannelId' => $salesChannelId,
                'shopwareCustomerId' => $customer->getId(),
                'rawData' => $sezzleCustomerResponse,
            ];
            if ($existingTokenized) {
                $customerEntity['id'] = $existingTokenized->getId();
            } else {
            }
            $this->sezzleCustomerRepository->upsert([$customerEntity], $context);
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('sezzleCustomerUuid', $sezzleCustomerResponse['uuid']));
            $savedCustomer = $this->sezzleCustomerRepository->search($criteria, $context)->first();
            if ($savedCustomer) {
            }
            return $savedCustomer;
        } catch (\Exception $e) {
            return null;
        }
    }
    public function getTokenizedCustomer(string $shopwareCustomerId, string $salesChannelId, Context $context): ?SezzleCustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shopwareCustomerId', $shopwareCustomerId));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('isTokenized', true));
        return $this->sezzleCustomerRepository->search($criteria, $context)->first();
    }
}
