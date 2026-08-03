<?php

declare(strict_types=1);

namespace Sezzle\Controller\Admin;

use Sezzle\Services\ConfigService;
use Sezzle\Services\WebhookManagementService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SezzleWebhookManagementController extends AbstractController
{
    public function __construct(
        private readonly WebhookManagementService $webhookManagementService,
        private readonly ConfigService $configService
    ) {
    }
    #[Route(path: '/api/_action/sezzle/webhook/delete-and-clear', name: 'api.action.sezzle.webhook.delete_and_clear', methods: ['POST'])]
    public function deleteAndClearWebhook(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->request->get('salesChannelId') ?? '';
        $webhookUuid = $this->configService->getConfig('webhookUuid', $salesChannelId);
        if (!$webhookUuid) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No webhook UUID found in configuration',
            ], 400);
        }
        $result = $this->webhookManagementService->deleteWebhook($webhookUuid, $salesChannelId);
        try {
            $configService = $this->container->get('Shopware\Core\System\SystemConfig\SystemConfigService');
            $configService->delete('Sezzle.config.webhookUuid', $salesChannelId ?: null);
        } catch (\Exception $e) {
        }
        $statusCode = $result['success'] ? 200 : 400;
        return new JsonResponse($result, $statusCode);
    }
    #[Route(path: '/api/_action/sezzle/webhook/status', name: 'api.action.sezzle.webhook.status', methods: ['GET'])]
    public function getWebhookStatus(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId') ?? '';
        $webhookUuid = $this->configService->getConfig('webhookUuid', $salesChannelId);
        return new JsonResponse([
            'configured' => !empty($webhookUuid),
            'webhookUuid' => $webhookUuid,
            'webhookUrl' => $request->getSchemeAndHttpHost() . '/sezzle/webhook',
        ]);
    }
    #[Route(path: '/api/_action/sezzle/webhook/list', name: 'api.action.sezzle.webhook.list', methods: ['POST'])]
    public function listWebhooks(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->request->get('salesChannelId') ?? '';
        try {
            $result = $this->webhookManagementService->listWebhooks($salesChannelId);
            return new JsonResponse([
                'success' => true,
                'webhooks' => $result['webhooks'] ?? [],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    #[Route(path: '/api/_action/sezzle/webhook/delete-by-uuid', name: 'api.action.sezzle.webhook.delete_by_uuid', methods: ['POST'])]
    public function deleteWebhookByUuid(Request $request, Context $context): JsonResponse
    {
        $webhookUuid = $request->request->get('webhookUuid');
        $salesChannelId = $request->request->get('salesChannelId') ?? '';
        if (!$webhookUuid) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook UUID is required',
            ], 400);
        }
        $result = $this->webhookManagementService->deleteWebhook($webhookUuid, $salesChannelId);
        $statusCode = $result['success'] ? 200 : 400;
        return new JsonResponse($result, $statusCode);
    }
}
