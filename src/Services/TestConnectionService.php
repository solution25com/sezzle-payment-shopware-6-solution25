<?php

declare(strict_types=1);

namespace Sezzle\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

readonly class TestConnectionService
{
    public function __construct(
        private SezzleClientService $sezzleClientService,
        private EntityRepository $salesChannelRepository,
    ) {
    }
    public function testAllConnections(Context $context): array
    {
        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context);
        $results = [];
        foreach ($salesChannels as $salesChannel) {
            /** @var SalesChannelEntity $salesChannel */
            $id = $salesChannel->getId();
            $name = (string) ($salesChannel->getTranslation('name') ?? $salesChannel->getName());
            try {
                $results[$name] = $this->sezzleClientService->testConnection($id);
            } catch (\Throwable $e) {
                $results[$name] = false;
            }
        }
        return $results;
    }
}
