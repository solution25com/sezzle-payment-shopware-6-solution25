<?php
namespace Sezzle\Storefront\Struct;
use Shopware\Core\Framework\Struct\Struct;
class CheckoutTemplateCustomData extends Struct
{
    public const HEADER_EXTENSION = 'sezzle_header';
    public const EXTENSION_NAME = 'sezzle';

    protected ?string $merchantId = null;
    protected ?string $mode = null;
    protected bool $enableHomepageBanner = false;

    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    public function setMerchantId(?string $merchantId): void
    {
        $this->merchantId = $merchantId;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): void
    {
        $this->mode = $mode;
    }

    public function isEnableHomepageBanner(): bool
    {
        return $this->enableHomepageBanner;
    }

    public function setEnableHomepageBanner(bool $enableHomepageBanner): void
    {
        $this->enableHomepageBanner = $enableHomepageBanner;
    }
}
