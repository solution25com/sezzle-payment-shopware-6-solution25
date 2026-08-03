<?php

declare(strict_types=1);

namespace Sezzle\Event;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

final class SezzlePaymentCompletedEvent extends Event
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $transactionId,
        public readonly Context $context,
    ) {
    }
}
