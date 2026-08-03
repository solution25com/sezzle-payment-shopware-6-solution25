<?php

declare(strict_types=1);

namespace Sezzle\Subscriber;

use Psr\Log\LoggerInterface;
use Sezzle\Gateways\SezzlePaymentHandler;
use Sezzle\Library\Constants\SezzleFields;
use Sezzle\Services\OrderTransactionMapper\OrderTransactionMapper;
use Sezzle\Services\SezzleClientService;
use Sezzle\Services\SezzleCustomerTokenizationService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPaidSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SezzleCustomerTokenizationService $tokenizationService,
        private readonly SezzleClientService $sezzleClientService,
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly LoggerInterface $logger
    ) {
    }
    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction.state.paid' => 'onOrderTransactionPaid',
        ];
    }
    public function onOrderTransactionPaid(OrderStateMachineStateChangeEvent $event): void
    {
        $context = $event->getContext();
        $order = $event->getOrder();
        try {
            $transaction = $order->getTransactions()?->first();
            if (!$transaction) {
                return;
            }
            $paymentMethod = $transaction->getPaymentMethod();
            if (!$paymentMethod || $paymentMethod->getHandlerIdentifier() !== SezzlePaymentHandler::class) {
                return;
            }
            $customFields = $order->getCustomFields() ?? [];
            $sezzleOrderUuid = $customFields[SezzleFields::ORDER_CF_ORDER_UUID] ?? null;
            $sezzleCustomerUuid = $customFields['sezzleCustomerUuid'] ?? null;
            if (!$sezzleOrderUuid) {
                $this->logger->warning('Sezzle order UUID not found in order custom fields', [
                    'orderId' => $order->getId(),
                ]);
                return;
            }
            $salesChannelId = $order->getSalesChannelId();
            $this->tokenizationService->tokenizeCustomerFromOrder($order, $customFields, $salesChannelId, $context);
            $this->logger->info('Successfully tokenized Sezzle customer', [
                'orderId' => $order->getId(),
                'customerId' => $order->getOrderCustomer()?->getCustomerId(),
                'sezzleCustomerUuid' => $sezzleCustomerUuid,
            ]);

            $this->captureAuthorizedOrder($order, $transaction, $customFields, $salesChannelId, $context);
        } catch (\Exception $e) {
            $this->logger->error('Failed to process Sezzle order on payment paid', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * In "auth" mode the funds are only authorized at checkout. When the order transaction is marked paid
     * (e.g. on fulfilment) we capture the authorized amount on Sezzle. "direct_capture" orders are already
     * captured by Sezzle at checkout completion, so they are skipped here.
     *
     * @param array<string, mixed> $customFields
     */
    private function captureAuthorizedOrder(
        OrderEntity $order,
        OrderTransactionEntity $transaction,
        array $customFields,
        string $salesChannelId,
        Context $context
    ): void {
        $sezzleOrderUuid = $customFields[SezzleFields::ORDER_CF_ORDER_UUID] ?? null;
        if (!$sezzleOrderUuid) {
            return;
        }

        // Already captured (by us, by the webhook, or direct-capture at checkout) → nothing to do.
        if (!empty($customFields[SezzleFields::ORDER_CF_CAPTURE_UUID])) {
            return;
        }

        // CAPTURE intent is captured by Sezzle at checkout; only AUTH needs a manual capture here.
        $intent = $customFields['sezzlePaymentIntent'] ?? null;
        if ($intent === 'CAPTURE') {
            return;
        }

        $amountTotal = $order->getAmountTotal();
        $currencyCode = $order->getCurrency()?->getIsoCode();
        if ($currencyCode === null) {
            // The currency association is not guaranteed on the state-change event; reload it.
            $reloaded = $this->orderTransactionMapper->getOrderTransactionsById($transaction->getId(), $context);
            $currencyCode = $reloaded?->getOrder()?->getCurrency()?->getIsoCode() ?? 'USD';
        }

        $body = [
            'capture_amount' => [
                'amount_in_cents' => (int) round($amountTotal * 100),
                'currency' => $currencyCode,
            ],
        ];

        try {
            $response = $this->sezzleClientService->captureOrder($sezzleOrderUuid, $body, $salesChannelId);
            $this->orderTransactionMapper->setSezzleCustomFieldFromOrder($order, $context, [
                SezzleFields::ORDER_CF_CAPTURE_UUID => $response['uuid'] ?? null,
                'sezzleCaptureRequest' => json_encode($body),
                'sezzleCaptureResponse' => json_encode($response),
            ]);
            $this->logger->info('Sezzle order captured on payment paid', [
                'orderId' => $order->getId(),
                'sezzleOrderUuid' => $sezzleOrderUuid,
                'captureUuid' => $response['uuid'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to capture Sezzle order on payment paid', [
                'orderId' => $order->getId(),
                'sezzleOrderUuid' => $sezzleOrderUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
