<?php
declare(strict_types=1);
namespace Sezzle\Controller\Admin;
use Sezzle\Services\WebhookManagementService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route(defaults: ['_routeScope' => ['api']])]
class SezzleWebhookController extends AbstractController
{
    public function __construct(
        private readonly WebhookManagementService $webhookManagementService
    ) {
    }
    #[Route(path: '/api/_action/sezzle/webhook/register', name: 'api.action.sezzle.webhook.register', methods: ['POST'])]
    public function registerWebhook(Request $request, Context $context): JsonResponse
    {
        $webhookUrl = $request->request->get('webhookUrl');
        $events = $request->request->get('events', []);
        $salesChannelId = $request->request->get('salesChannelId') ?? '';
        if (!$webhookUrl) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook URL is required',
            ], 400);
        }
        if (empty($events)) {
            $events = $this->webhookManagementService->getRecommendedEvents();
        }
        $result = $this->webhookManagementService->registerWebhook($webhookUrl, $events, $salesChannelId);
        $statusCode = $result['success'] ? 200 : 400;
        return new JsonResponse($result, $statusCode);
    }
    #[Route(path: '/api/_action/sezzle/webhook/test', name: 'api.action.sezzle.webhook.test', methods: ['POST'])]
    public function testWebhook(Request $request, Context $context): JsonResponse
    {
        $webhookUrl = $request->request->get('webhookUrl');
        $salesChannelId = $request->request->get('salesChannelId') ?? '';
        if (!$webhookUrl) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook URL is required',
            ], 400);
        }
        $result = $this->webhookManagementService->testWebhook($webhookUrl, $salesChannelId);
        $statusCode = $result['success'] ? 200 : 400;
        return new JsonResponse($result, $statusCode);
    }
    #[Route(path: '/api/_action/sezzle/webhook/delete', name: 'api.action.sezzle.webhook.delete', methods: ['POST'])]
    public function deleteWebhook(Request $request, Context $context): JsonResponse
    {
        $webhookUuid = $request->request->get('webhookUuid');
        $salesChannelId = $request->request->get('salesChannelId');
        if (!$webhookUuid || !$salesChannelId) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Webhook UUID and sales channel ID are required',
            ], 400);
        }
        $result = $this->webhookManagementService->deleteWebhook($webhookUuid, $salesChannelId);
        $statusCode = $result['success'] ? 200 : 400;
        return new JsonResponse($result, $statusCode);
    }
    #[Route(path: '/api/_action/sezzle/webhook/auto-configure', name: 'api.action.sezzle.webhook.auto_configure', methods: ['POST'])]
    public function autoConfigureWebhook(Request $request, Context $context): JsonResponse
    {
        $domain = $request->request->get('domain');
        $salesChannelId = '019a777a2ce173e2b5a12448f9fef11f';
        if (!$domain) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Domain is required',
            ], 400);
        }
        $result = $this->webhookManagementService->autoConfigureWebhook($domain, $salesChannelId);
        $statusCode = $result['success'] ? 200 : 400;
        return new JsonResponse($result, $statusCode);
    }
}
