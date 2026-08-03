<?php

declare(strict_types=1);

namespace Sezzle\Storefront\Controller;

use JsonException;
use Psr\Log\LoggerInterface;
use Sezzle\Services\ConfigService;
use Sezzle\Services\OrderTransactionMapper\OrderTransactionMapper;
use Sezzle\Services\PaymentTransactionStateHandler\SezzleTransactionStateHandler;
use Sezzle\Services\SezzleWebhookSignatureVerifier;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class WebhookController extends StorefrontController
{
    private const EVENT_TO_ACTION = [
        'order.authorized' => 'authorized',
        'order.captured' => 'captured',
        'order.released' => 'released',
        'order.cancelled' => 'cancel',
        'Order.authorized' => 'authorized',
        'Order.captured' => 'captured',
        'Order.released' => 'released',
        'Order.cancelled' => 'cancel',
    ];

    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly SezzleTransactionStateHandler $sezzleTransactionStateHandler,
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly SezzleWebhookSignatureVerifier $signatureVerifier,
        private readonly ConfigService $configService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/sezzle/webhook',
        name: 'frontend.sezzle.webhook',
        methods: ['POST']
    )]
    public function webhook(Request $request, SalesChannelContext $context): Response
    {
        $swContext = $context->getContext();
        $payload = $this->parsePayload($request);
        if ($payload === null) {
            return $this->respond(false, 'Invalid JSON payload', Response::HTTP_BAD_REQUEST);
        }

        $sezzleOrderUuid = $this->extractSezzleOrderUuid($payload);
        if ($sezzleOrderUuid === null || $sezzleOrderUuid === '') {
            $this->logger->warning('Sezzle webhook missing order UUID');

            return $this->respond(false, 'Missing order UUID', Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderTransactionMapper->findOrderBySezzleOrderUuid($sezzleOrderUuid, $swContext);
        if ($order === null) {
            $this->logger->warning('Sezzle webhook could not match order', ['orderUuid' => $sezzleOrderUuid]);

            return $this->respond(false, 'Order not found for orderUuid', Response::HTTP_NOT_FOUND);
        }

        $salesChannelId = $order->getSalesChannelId();
        $privateKey = $this->configService->getMerchantPrivateKey($salesChannelId);
        if (!$this->signatureVerifier->verify($request, $privateKey)) {
            $this->logger->warning('Sezzle webhook signature verification failed', [
                'orderId' => $order->getId(),
            ]);

            return $this->respond(false, 'Invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $webhookEventUuid = $payload['uuid'] ?? null;
        if (is_string($webhookEventUuid) && $this->isDuplicateWebhookEvent($order, $webhookEventUuid)) {
            return $this->respond(true, 'Webhook already processed');
        }

        try {
            $customFields = $order->getCustomFields() ?? [];
            $existingWebhooks = $customFields['sezzleWebhookPayloads'] ?? [];
            $eventType = $payload['event'] ?? $payload['event_type'] ?? $payload['eventType'] ?? null;
            $existingWebhooks[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'event_type' => $eventType,
                'webhook_uuid' => $webhookEventUuid,
                'payload' => $payload,
            ];

            $this->orderTransactionMapper->updateSezzleFieldsFromWebhook($order, $swContext, $this->mapWebhookPayload($payload));
            $this->orderTransactionMapper->updateSezzleCustomer($order, $swContext, $this->mapWebhookPayload($payload));

            $processedEvents = $customFields['sezzleProcessedWebhookEvents'] ?? [];
            if (is_string($webhookEventUuid) && $webhookEventUuid !== '') {
                $processedEvents[] = $webhookEventUuid;
            }

            $this->orderTransactionMapper->setSezzleCustomFieldFromOrder($order, $swContext, [
                'sezzleWebhookPayloads' => $existingWebhooks,
                'sezzleProcessedWebhookEvents' => array_values(array_unique($processedEvents)),
            ]);

            $transactionId = $this->getFirstTransactionId($order);
            if ($transactionId === null) {
                $this->logger->warning('Sezzle webhook: order has no transactions', [
                    'orderId' => $order->getId(),
                ]);

                return $this->respond(false, 'Order has no transactions', Response::HTTP_BAD_REQUEST);
            }

            if ($eventType === 'order.refunded' || $eventType === 'Order.refunded') {
                $refundAmountInCents = (int) ($payload['data']['refund']['amount']['amount_in_cents'] ?? 0);
                $orderTotalInCents = (int) ($order->getAmountTotal() * 100);
                $isFullRefund = $refundAmountInCents > 0 && $refundAmountInCents >= $orderTotalInCents;

                try {
                    if ($isFullRefund) {
                        $this->sezzleTransactionStateHandler->handleRefunded($transactionId, $swContext);
                    } else {
                        $this->sezzleTransactionStateHandler->handleRefundedPartially($transactionId, $swContext);
                    }
                } catch (IllegalTransitionException $e) {
                    $this->logger->info('Sezzle refund webhook: refund state transition skipped', [
                        'orderId' => $order->getId(),
                        'fullRefund' => $isFullRefund,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->logger->info('Sezzle refund webhook processed', [
                    'orderId' => $order->getId(),
                    'refundAmountInCents' => $refundAmountInCents,
                    'orderTotalInCents' => $orderTotalInCents,
                    'fullRefund' => $isFullRefund,
                ]);

                return $this->respond(true, $isFullRefund ? 'Order fully refunded' : 'Order partially refunded');
            }

            $actionMethod = is_string($eventType) ? (self::EVENT_TO_ACTION[$eventType] ?? null) : null;
            match ($actionMethod) {
                'authorized' => $this->sezzleTransactionStateHandler->handleAuthorized($transactionId, $swContext),
                'captured' => $this->sezzleTransactionStateHandler->handleCaptured($transactionId, $swContext),
                'released' => $this->sezzleTransactionStateHandler->handleReleased($transactionId, $swContext),
                'cancel' => $this->transactionStateHandler->cancel($transactionId, $swContext),
                default => $this->logger->notice('Sezzle webhook received event with no action', [
                    'eventType' => $eventType,
                    'payload' => $payload,
                ]),
            };

            return $this->respond(true, 'Webhook processed');
        } catch (\Throwable $e) {
            $this->logger->error('Sezzle webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respond(false, 'Webhook processing failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function parsePayload(Request $request): ?array
    {
        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    private function extractSezzleOrderUuid(array $payload): ?string
    {
        $dataUuid = $payload['data']['uuid'] ?? null;
        if (is_string($dataUuid) && $dataUuid !== '') {
            return $dataUuid;
        }

        $legacyUuid = $payload['order']['uuid'] ?? $payload['orderUuid'] ?? null;

        return is_string($legacyUuid) && $legacyUuid !== '' ? $legacyUuid : null;
    }

    private function mapWebhookPayload(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return [
            'orderUuid' => $data['uuid'] ?? $payload['order']['uuid'] ?? $payload['orderUuid'] ?? null,
            'checkoutUuid' => $data['checkout']['uuid'] ?? $payload['checkoutUuid'] ?? null,
            'sessionUuid' => $payload['sessionUuid'] ?? null,
            'status' => $payload['event'] ?? $payload['event_type'] ?? $payload['eventType'] ?? null,
            'amount' => $data['authorization']['authorization_amount']['amount_in_cents']
                ?? $data['capture']['amount']['amount_in_cents']
                ?? null,
            'currency' => $data['authorization']['authorization_amount']['currency']
                ?? $data['capture']['amount']['currency']
                ?? null,
            'captureUuid' => $data['capture']['uuid'] ?? null,
            'refundUuid' => $data['refund']['uuid'] ?? null,
        ];
    }

    private function isDuplicateWebhookEvent(OrderEntity $order, string $webhookEventUuid): bool
    {
        $processed = $order->getCustomFields()['sezzleProcessedWebhookEvents'] ?? [];

        return is_array($processed) && in_array($webhookEventUuid, $processed, true);
    }

    private function getFirstTransactionId(OrderEntity $order): ?string
    {
        $primary = null;
        foreach ($order->getTransactions() ?? [] as $transaction) {
            if ($primary === null || $transaction->getCreatedAt() > $primary->getCreatedAt()) {
                $primary = $transaction;
            }
        }

        return $primary?->getId();
    }

    private function respond(bool $ok, string $message, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(
            ['success' => $ok, 'message' => $message],
            $statusCode
        );
    }
}
