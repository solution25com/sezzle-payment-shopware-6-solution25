<?php

declare(strict_types=1);

namespace Sezzle\Services;

use Sezzle\Exception\SezzleApiException;

class WebhookManagementService
{
    public function __construct(
        private readonly SezzleClientService $sezzleClientService,
        private readonly ConfigService $configService
    ) {
    }
    public function registerWebhook(string $webhookUrl, array $events, string $salesChannelId): array
    {
        try {
            if (empty($events)) {
                $events = $this->getRecommendedEvents();
            }
            $body = [
                'url' => $webhookUrl,
                'events' => $events,
            ];
            $response = $this->sezzleClientService->createWebhook($body, $salesChannelId);
            return [
                'success' => true,
                'webhookUuid' => $response['uuid'] ?? null,
                'webhookId' => $response['id'] ?? null,
                'url' => $response['url'] ?? null,
                'events' => $response['events'] ?? [],
                'data' => $response,
            ];
        } catch (\Exception $e) {
            $params = method_exists($e, 'getParameters') ? $e->getParameters() : [];
            $isDuplicate = isset($params['isDuplicate']) && $params['isDuplicate'] === true;
            if ($isDuplicate) {
                return [
                    'success' => true,
                    'message' => 'Webhook was already registered (this is OK)',
                    'isDuplicate' => true,
                ];
            }
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => $params,
            ];
        }
    }
    public function testWebhook(string $webhookUrl, string $salesChannelId): array
    {
        try {
            $body = [
                'url' => $webhookUrl,
                'event' => 'order.authorized',
            ];
            $response = $this->sezzleClientService->testWebhook($body, $salesChannelId);
            return [
                'success' => true,
                'data' => $response,
                'message' => 'Webhook test sent successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => method_exists($e, 'getParameters') ? $e->getParameters() : [],
            ];
        }
    }
    public function listWebhooks(string $salesChannelId): array
    {
        try {
            $response = $this->sezzleClientService->listWebhooks($salesChannelId);
            return $response;
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function deleteWebhook(string $webhookUuid, string $salesChannelId): array
    {
        try {
            $response = $this->sezzleClientService->deleteWebhook($webhookUuid, $salesChannelId);
            return [
                'success' => true,
                'message' => 'Webhook deleted successfully',
                'data' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    public function getRecommendedEvents(): array
    {
        return [
            'order.authorized',
            'order.captured',
            'order.cancelled',
            'order.released',
        ];
    }
    public function autoConfigureWebhook(string $domain, string $salesChannelId): array
    {
        $webhookUrl = Endpoints::callbackUrl($domain);
        $configuredEvents = $this->configService->getConfig('webhookEvents', $salesChannelId);
        $events = !empty($configuredEvents) && is_array($configuredEvents)
            ? $configuredEvents
            : $this->getRecommendedEvents();
        try {
            $existingWebhooks = $this->sezzleClientService->listWebhooks($salesChannelId);
            if (isset($existingWebhooks['webhooks']) && is_array($existingWebhooks['webhooks'])) {
                foreach ($existingWebhooks['webhooks'] as $webhook) {
                    if (isset($webhook['url']) && $webhook['url'] === $webhookUrl) {
                        $webhookUuidToDelete = $webhook['uuid'] ?? $webhook['id'] ?? null;
                        if ($webhookUuidToDelete) {
                            try {
                                $this->deleteWebhook($webhookUuidToDelete, $salesChannelId);
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }
        $existingWebhookUuid = $this->configService->getConfig('webhookUuid', $salesChannelId);
        if ($existingWebhookUuid) {
            try {
                $this->deleteWebhook($existingWebhookUuid, $salesChannelId);
            } catch (\Exception $e) {
            }
        }
        return $this->registerWebhook($webhookUrl, $events, $salesChannelId);
    }
}
