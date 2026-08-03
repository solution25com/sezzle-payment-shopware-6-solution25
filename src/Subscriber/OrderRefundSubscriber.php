<?php

declare(strict_types=1);

namespace Sezzle\Subscriber;

use Psr\Log\LoggerInterface;
use Sezzle\Gateways\SezzlePaymentHandler;
use Sezzle\Services\SezzleClientService;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderRefundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SezzleClientService $sezzleClientService,
        private readonly LoggerInterface $logger
    ) {
    }
    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction.state.refunded' => 'onOrderTransactionRefunded',
            'state_enter.order_transaction.state.refunded_partially' => 'onOrderTransactionRefunded',
        ];
    }
    public function onOrderTransactionRefunded(OrderStateMachineStateChangeEvent $event): void
    {
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
            $sezzleOrderUuid = $customFields['sezzleOrderUuid'] ?? null;
            if (!$sezzleOrderUuid) {
                $this->logger->warning('Sezzle order UUID not found for refund', [
                    'orderId' => $order->getId(),
                ]);
                return;
            }
            if (!empty($customFields['sezzleRefundUuid'])) {
                $this->logger->info('Sezzle refund already recorded from webhook; skipping outbound refund to avoid double refund', [
                    'orderId' => $order->getId(),
                    'sezzleRefundUuid' => $customFields['sezzleRefundUuid'],
                ]);
                return;
            }
            $refundAmount = $order->getAmountTotal();
            $currency = $order->getCurrency();
            $currencyCode = $currency ? $currency->getIsoCode() : 'USD';
            $refundData = [
                'amount' => [
                    'amount_in_cents' => (int) round($refundAmount * 100),
                    'currency' => $currencyCode,
                ],
            ];
            $salesChannelId = $order->getSalesChannelId();
            $response = $this->sezzleClientService->refundOrder($sezzleOrderUuid, $refundData, $salesChannelId);
            $this->logger->info('Successfully refunded Sezzle order', [
                'orderId' => $order->getId(),
                'sezzleOrderUuid' => $sezzleOrderUuid,
                'refundUuid' => $response['uuid'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to refund Sezzle order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
