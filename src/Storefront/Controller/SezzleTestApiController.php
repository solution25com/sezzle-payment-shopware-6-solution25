<?php

declare(strict_types=1);

namespace Sezzle\Storefront\Controller;

use Sezzle\Services\TestConnectionService;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class SezzleTestApiController extends StorefrontController
{
    public function __construct(
        private readonly TestConnectionService $testConnectionService,
    ) {
    }
    #[Route(path: '/sezzle/test-connection', name: 'frontend.sezzle.test_connection', methods: ['GET'])]
    public function testConnection(Context $context): JsonResponse
    {
        $results = $this->testConnectionService->testAllConnections($context);
        return new JsonResponse(['results' => $results]);
    }
}
