<?php
declare(strict_types=1);
namespace Sezzle\Services;
use Shopware\Core\System\SystemConfig\SystemConfigService;
class ConfigService
{
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }
    public function getConfig(string $configName, ?string $salesChannelId = null): mixed
    {
        return $this->systemConfigService->get('Sezzle.config.' . trim($configName), $salesChannelId);
    }
}
