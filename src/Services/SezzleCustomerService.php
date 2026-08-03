<?php

declare(strict_types=1);

namespace Sezzle\Services;

use Sezzle\DataAbstractionLayer\Entity\SezzleCustomer\SezzleCustomerCollection;
use Sezzle\DataAbstractionLayer\Entity\SezzleCustomer\SezzleCustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class SezzleCustomerService
{
    public function __construct(
        private readonly EntityRepository $sezzleCustomerRepository,
        private readonly SezzleClientService $sezzleClientService
    ) {
    }
    public function syncCustomersFromSezzle(string $salesChannelId, Context $context): array
    {
        $synced = 0;
        $errors = [];
        try {
            $offset = 0;
            $limit = 100;
            $hasMore = true;
            while ($hasMore) {
                $customersResponse = $this->sezzleClientService->listCustomers([
                    'limit' => $limit,
                    'offset' => $offset,
                ], $salesChannelId);
                $customers = $customersResponse;
                if (empty($customers)) {
                    $hasMore = false;
                    break;
                }
                foreach ($customers as $customerData) {
                    try {
                        $customerUuid = $customerData['uuid'] ?? null;
                        if ($customerUuid) {
                            $fullCustomerData = $this->sezzleClientService->getCustomer($customerUuid, $salesChannelId);
                            $this->createOrUpdateCustomer($fullCustomerData, $salesChannelId, $context);
                            $synced++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'customer_uuid' => $customerData['uuid'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
                $offset += $limit;
                if (count($customers) < $limit) {
                    $hasMore = false;
                }
            }
            return [
                'success' => true,
                'synced' => $synced,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'synced' => $synced,
                'errors' => array_merge($errors, [['error' => $e->getMessage()]]),
            ];
        }
    }
    public function createOrUpdateCustomer(array $customerData, string $salesChannelId, Context $context): void
    {
        $customerUuid = $customerData['uuid'] ?? null;
        if (!$customerUuid) {
            throw new \InvalidArgumentException('Customer UUID is required');
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sezzleCustomerUuid', $customerUuid));
        /** @var SezzleCustomerEntity|null $existing */
        $existing = $this->sezzleCustomerRepository->search($criteria, $context)->first();
        $data = [
            'sezzleCustomerUuid' => $customerUuid,
            'merchantUuid' => $customerData['merchant_uuid'] ?? null,
            'firstName' => $customerData['first_name'] ?? null,
            'lastName' => $customerData['last_name'] ?? null,
            'email' => $customerData['email'] ?? null,
            'phone' => $customerData['phone'] ?? null,
            'dateOfBirth' => isset($customerData['date_of_birth'])
                ? new \DateTime($customerData['date_of_birth'])
                : null,
            'billingAddress' => $customerData['billing_address'] ?? null,
            'shippingAddress' => $customerData['shipping_address'] ?? null,
            'isTokenized' => true,
            'tokenizedAt' => isset($customerData['tokenized_at'])
                ? new \DateTime($customerData['tokenized_at'])
                : null,
            'salesChannelId' => $salesChannelId,
            'rawData' => $customerData,
        ];
        if ($existing) {
            $data['id'] = $existing->getId();
        }
        $this->sezzleCustomerRepository->upsert([$data], $context);
    }
    public function getCustomerByUuid(string $customerUuid, Context $context): ?SezzleCustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sezzleCustomerUuid', $customerUuid));
        $criteria->addAssociation('shopwareCustomer');
        $criteria->addAssociation('salesChannel');
        /** @var SezzleCustomerEntity|null $result */
        $result = $this->sezzleCustomerRepository->search($criteria, $context)->first();
        return $result;
    }
    public function getAllCustomers(Context $context, ?int $limit = null, ?int $offset = null): SezzleCustomerCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('shopwareCustomer');
        $criteria->addAssociation('salesChannel');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        if ($limit !== null) {
            $criteria->setLimit($limit);
        }
        if ($offset !== null) {
            $criteria->setOffset($offset);
        }
        /** @var SezzleCustomerCollection $collection */
        $collection = $this->sezzleCustomerRepository->search($criteria, $context)->getEntities();
        return $collection;
    }
    public function getTotalCount(Context $context): int
    {
        $criteria = new Criteria();
        return $this->sezzleCustomerRepository->search($criteria, $context)->getTotal();
    }
    public function chargeCustomer(
        string $customerUuid,
        array $orderData,
        string $salesChannelId,
        Context $context
    ): array {
        try {
            $response = $this->sezzleClientService->chargeCustomer($customerUuid, $orderData, $salesChannelId);
            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
