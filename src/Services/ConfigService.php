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

    public function getMerchantPrivateKey(?string $salesChannelId = null): string
    {
        $mode = $this->getConfig('mode', $salesChannelId);
        $isProd = $mode === 'live';
        $key = $isProd
            ? $this->getConfig('apiPasswordLive', $salesChannelId)
            : $this->getConfig('apiPasswordSandbox', $salesChannelId);

        return is_string($key) ? $key : '';
    }

    public function isLaunchFeatureEnabled(string $featureName, ?string $salesChannelId = null): bool
    {
        return (bool) ($this->getConfig($featureName, $salesChannelId) ?? false);
    }
}
