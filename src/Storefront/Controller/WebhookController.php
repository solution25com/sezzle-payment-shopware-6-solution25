<?php
declare(strict_types=1);
namespace Sezzle\Storefront\Controller;
use JsonException;
use Psr\Log\LoggerInterface;
use Sezzle\Library\Constants\SezzleFields;
use Sezzle\Services\OrderTransactionMapper\OrderTransactionMapper;
use Sezzle\Services\PaymentTransactionStateHandler\SezzleTransactionStateHandler;
use Sezzle\Services\SezzleClientService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
#[Route(defaults: ['_routeScope' => ['storefront']])]
class WebhookController extends StorefrontController
{
    private const EVENT_TO_ACTION = [
        'Order.authorized' => 'authorized',
        'Order.captured' => 'captured',
        'Order.refunded' => 'refunded',
        'Order.released' => 'released',
        'Order.cancelled' => 'cancel',
    ];
    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly SezzleTransactionStateHandler $sezzleTransactionStateHandler,
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly SezzleClientService $sezzleClientService,
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
        if (!$this->isValidSignature($request)) {
            $this->logger->warning('Sezzle webhook signature verification failed');
            return $this->respond(false, 'Invalid signature');
        }
        $payload = $this->parsePayload($request);
        if ($payload === null) {
            return $this->respond(false, 'Invalid JSON payload');
        }
        $orderUuid = $payload['order']['uuid'] ?? $payload['orderUuid'] ?? null;
        if ($orderUuid === null || $orderUuid === '') {
            $this->logger->warning('Sezzle webhook missing orderUuid');
            return $this->respond(false, 'Missing orderUuid');
        }
        try {
            $order = $this->orderTransactionMapper->findOrderBySezzleOrderUuid($orderUuid, $swContext);
            if ($order === null) {
                $this->logger->warning('Sezzle webhook could not match order', [
                    'orderUuid' => $orderUuid,
                ]);
                return $this->respond(false, 'Order not found for orderUuid');
            }
            $customFields = $order->getCustomFields() ?? [];
            $existingWebhooks = $customFields['sezzleWebhookPayloads'] ?? [];
            $existingWebhooks[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'event_type' => $eventType,
                'payload' => $payload,
            ];
            $this->orderTransactionMapper->updateSezzleFieldsFromWebhook($order, $swContext, $payload);
            $this->orderTransactionMapper->updateSezzleCustomer($order, $swContext, $payload);
            $this->orderTransactionMapper->setSezzleCustomFieldFromOrder($order, $swContext, [
                'sezzleWebhookPayloads' => $existingWebhooks,
            ]);
            $transactionId = $this->getFirstTransactionId($order);
            if ($transactionId === null) {
                $this->logger->warning('Sezzle webhook: order has no transactions', [
                    'orderId' => $order->getId(),
                    'payload' => $payload,
                ]);
                return $this->respond(false, 'Order has no transactions');
            }
            $eventType = $payload['event_type'] ?? $payload['eventType'] ?? null;
            $actionMethod = $eventType !== null ? (self::EVENT_TO_ACTION[$eventType] ?? null) : null;
            match ($actionMethod) {
                'authorized' => $this->sezzleTransactionStateHandler->handleAuthorized($transactionId, $swContext),
                'captured' => $this->sezzleTransactionStateHandler->handleCaptured($transactionId, $swContext),
                'refunded' => $this->sezzleTransactionStateHandler->handleRefunded($transactionId, $swContext),
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
            return $this->respond(false, 'Webhook processing failed');
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
    private function getFirstTransactionId(OrderEntity $order): ?string
    {
        return $order->getTransactions()?->first()?->getId();
    }
    private function respond(bool $ok, string $message): JsonResponse
    {
        return new JsonResponse(
            ['success' => $ok, 'message' => $message],
            Response::HTTP_OK
        );
    }
    private function isValidSignature(Request $request): bool
    {
        $secret = getenv('SEZZLE_WEBHOOK_SECRET') ?: (getenv('APP_SECRET') ?: ($_ENV['APP_SECRET'] ?? ''));
        if ($secret === '') {
            return false;
        }
        return true;
    }
}
