<?php

namespace Sezzle\Storefront\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CheckoutTemplateCustomData extends Struct
{
    public const EXTENSION_NAME = 'sezzle';

    protected ?string $merchantId = null;
    protected ?string $mode = null;

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
}
