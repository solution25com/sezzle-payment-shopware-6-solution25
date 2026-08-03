<?php

namespace Sezzle\Services\OrderTransactionMapper;

use Sezzle\Library\Constants\SezzleFields;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderTransactionMapper
{
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $customerRepository
    ) {
    }
    public function getOrderTransactionsById(string $transactionId, Context $context)
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.orderCustomer.customer.addresses');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.billingAddress');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('order.billingAddress.countryState');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.deliveries');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('paymentMethod');
        return $this->orderTransactionRepository->search($criteria, $context)->first();
    }
    public function setSezzleCustomFieldFromOrder(OrderEntity $order, Context $context, array $sezzleData): void
    {
        $this->orderRepository->update([[
            'id' => $order->getId(),
            'customFields' => array_merge(
                $order->getCustomFields() ?? [],
                $sezzleData
            ),
        ]], $context);
    }
    public function updateSezzleFieldsFromWebhook(OrderEntity $order, Context $context, array $webhookData): void
    {
        $fieldsToStore = [
            SezzleFields::ORDER_CF_ORDER_UUID => $webhookData['orderUuid'] ?? null,
            SezzleFields::ORDER_CF_CHECKOUT_UUID => $webhookData['checkoutUuid'] ?? null,
            SezzleFields::ORDER_CF_SESSION_UUID => $webhookData['sessionUuid'] ?? null,
            SezzleFields::ORDER_CF_STATUS => $webhookData['status'] ?? null,
            SezzleFields::ORDER_CF_AMOUNT => $webhookData['amount'] ?? null,
            SezzleFields::ORDER_CF_CURRENCY => $webhookData['currency'] ?? null,
            SezzleFields::ORDER_CF_CAPTURE_UUID => $webhookData['captureUuid'] ?? null,
            SezzleFields::ORDER_CF_REFUND_UUID => $webhookData['refundUuid'] ?? null,
        ];
        $this->orderRepository->update([[
            'id' => $order->getId(),
            'customFields' => array_merge(
                $order->getCustomFields() ?? [],
                $fieldsToStore
            ),
        ]], $context);
    }
    public function updateSezzleCustomer(OrderEntity $order, Context $context, array $webhookData): void
    {
        $orderCustomer = $order->getOrderCustomer();
        $customerId = $orderCustomer?->getCustomerId();
        if ($customerId === null) {
            return;
        }
        $fieldToStore = [
            SezzleFields::CUSTOMER_CF_ORDER_UUID => $webhookData['orderUuid'] ?? null,
            SezzleFields::CUSTOMER_CF_CHECKOUT_UUID => $webhookData['checkoutUuid'] ?? null,
        ];
        $this->customerRepository->update([[
            'id' => $customerId,
            'customFields' => array_merge(
                $orderCustomer->getCustomer()?->getCustomFields() ?? [],
                $fieldToStore
            ),
        ]], $context);
    }
    public function findOrderBySezzleOrderUuid(string $orderUuid, Context $context): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.sezzleOrderUuid', $orderUuid));
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('order');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
        $order = $this->orderRepository->search($criteria, $context)->first();
        if ($order instanceof OrderEntity) {
            return $order;
        }
        return null;
    }
    public function storeSezzleChargeResponse(string $orderId, array $sezzleResponse, Context $context): void
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return;
        }
        $transactions = $order->getTransactions();
        if (!$transactions || $transactions->count() === 0) {
            return;
        }
        $transaction = $transactions->last();
        if ($transaction === null) {
            return;
        }
        $this->orderTransactionRepository->update([[
            'id' => $transaction->getId(),
            'customFields' => array_merge(
                $transaction->getCustomFields() ?? [],
                [
                    SezzleFields::SEZZLE_ORDER_UUID => $sezzleResponse['uuid'] ?? null,
                    SezzleFields::SEZZLE_CHARGE_RESPONSE => $sezzleResponse,
                    'sezzleChargedAt' => date('Y-m-d H:i:s'),
                ]
            ),
        ]], $context);
    }
}
