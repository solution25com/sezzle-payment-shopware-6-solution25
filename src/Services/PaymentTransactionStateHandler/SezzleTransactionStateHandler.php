<?php
namespace Sezzle\Services\PaymentTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
readonly class SezzleTransactionStateHandler
{
    public function __construct(
        private OrderTransactionStateHandler $transactionStateHandler
    ) {
    }
    public function handleAuthorized(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->authorize($transactionId, $context);
    }
    public function handleCaptured(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->paid($transactionId, $context);
    }
    public function handleRefunded(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->refund($transactionId, $context);
    }
    public function handleReleased(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->cancel($transactionId, $context);
    }
}
