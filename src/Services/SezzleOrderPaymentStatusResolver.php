<?php

declare(strict_types=1);

namespace Sezzle\Services;

use Sezzle\Library\Constants\SezzlePaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;

final class SezzleOrderPaymentStatusResolver
{
    public function __construct(
        private readonly SezzleClientService $sezzleClientService,
        private readonly ConfigService $configService,
        private readonly OrderTransactionStateHandler $transactionStateHandler
    ) {
    }

    public function resolveFromProviderOrder(array $providerOrder, string $salesChannelId): string
    {
        $authorization = $providerOrder['authorization'] ?? null;

        $captures = (is_array($authorization) ? ($authorization['captures'] ?? null) : null)
            ?? ($providerOrder['captures'] ?? null);
        if (is_array($captures) && $captures !== []) {
            return SezzlePaymentStatus::VERIFIED_CAPTURED;
        }

        if (!is_array($authorization)) {
            return SezzlePaymentStatus::PENDING;
        }

        if (($authorization['approved'] ?? false) !== true) {
            return SezzlePaymentStatus::DECLINED;
        }

        $intent = $this->resolvePaymentIntent($salesChannelId);

        return $intent === 'CAPTURE'
            ? SezzlePaymentStatus::VERIFIED_CAPTURED
            : SezzlePaymentStatus::VERIFIED_AUTHORIZED;
    }

    public function fetchAndResolve(string $sezzleOrderUuid, string $salesChannelId): string
    {
        $providerOrder = $this->sezzleClientService->getOrder($sezzleOrderUuid, $salesChannelId);

        return $this->resolveFromProviderOrder($providerOrder, $salesChannelId);
    }

    public function applyToTransaction(string $status, string $transactionId, string $salesChannelId, Context $context): void
    {
        match ($status) {
            SezzlePaymentStatus::VERIFIED_CAPTURED => $this->transactionStateHandler->paid($transactionId, $context),
            SezzlePaymentStatus::VERIFIED_AUTHORIZED => $this->transactionStateHandler->authorize($transactionId, $context),
            SezzlePaymentStatus::DECLINED => $this->transactionStateHandler->fail($transactionId, $context),
            default => null,
        };
    }

    public function resolvePaymentIntent(?string $salesChannelId = null): string
    {
        $authorizeAndCapture = $this->configService->getConfig('authorizeAndCapture', $salesChannelId) ?? 'auth';

        return $authorizeAndCapture === 'direct_capture' ? 'CAPTURE' : 'AUTH';
    }
}
