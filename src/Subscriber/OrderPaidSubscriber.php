<?php
declare(strict_types=1);
namespace Sezzle\Subscriber;
use Psr\Log\LoggerInterface;
use Sezzle\Gateways\SezzlePaymentHandler;
use Sezzle\Services\SezzleCustomerTokenizationService;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
class OrderPaidSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SezzleCustomerTokenizationService $tokenizationService,
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
        if (!$order) {
            return;
        }
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
            $sezzleCustomerUuid = $customFields['sezzleCustomerUuid'] ?? null;
            $customerTokenized = $customFields['sezzleCustomerTokenized'] ?? false;
            if (!$sezzleOrderUuid) {
                $this->logger->warning('Sezzle order UUID not found in order custom fields', [
                    'orderId' => $order->getId(),
                ]);
                return;
            }
            if (!$sezzleCustomerUuid) {
            }
            $salesChannelId = $order->getSalesChannelId();
            $result = $this->tokenizationService->tokenizeCustomerFromOrder($order, $customFields, $salesChannelId, $context);
            $this->logger->info('Successfully tokenized Sezzle customer', [
                'orderId' => $order->getId(),
                'customerId' => $order->getOrderCustomer()?->getCustomerId(),
                'sezzleCustomerUuid' => $sezzleCustomerUuid,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to tokenize Sezzle customer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
