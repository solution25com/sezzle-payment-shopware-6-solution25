<?php

declare(strict_types=1);

namespace Sezzle\Controller\Admin;

use Sezzle\Services\WebhookManagementService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SezzleWebhookController extends AbstractController
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly WebhookManagementService $webhookManagementService,
        private readonly EntityRepository $salesChannelRepository
    ) {
    }
    #[Route(path: '/api/_action/sezzle/webhook/register', name: 'api.action.sezzle.webhook.register', methods: ['POST'])]
    public function registerWebhook(Request $request, Context $context): JsonResponse
    {
        $webhookUrl = $request->request->get('webhookUrl');
        $events = $request->request->all()['events'] ?? [];
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
        if (!$domain) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Domain is required',
            ], 400);
        }

        $salesChannelIds = $this->salesChannelRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('active', true)),
            $context
        )->getIds();

        if ($salesChannelIds === []) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No active sales channels found',
            ], 400);
        }

        $results = [];
        $succeeded = 0;
        foreach ($salesChannelIds as $salesChannelId) {
            $salesChannelId = (string) $salesChannelId;
            $result = $this->webhookManagementService->autoConfigureWebhook($domain, $salesChannelId);
            $results[$salesChannelId] = $result;
            if (($result['success'] ?? false) === true) {
                ++$succeeded;
            }
        }

        return new JsonResponse([
            'success' => $succeeded > 0,
            'configured' => $succeeded,
            'total' => \count($salesChannelIds),
            'salesChannels' => $results,
        ], $succeeded > 0 ? 200 : 400);
    }
}
