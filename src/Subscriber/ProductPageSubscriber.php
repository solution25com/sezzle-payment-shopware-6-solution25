<?php

declare(strict_types=1);

namespace Sezzle\Subscriber;

use Sezzle\Services\ConfigService;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $enablePriceBreakdownProduct = (bool) ($this->configService->getConfig(
            'enablePriceBreakdownProduct',
            $salesChannelId
        ) ?? true);

        $page = $event->getPage();
        $product = $page->getProduct();

        if (!$product) {
            return;
        }

        $price = $product->getCalculatedPrice();
        $amount = $price ? (float) $price->getTotalPrice() : 0.0;
        $currency = $salesChannelContext->getCurrency()->getIsoCode();

        $page->addExtension('sezzle', new ArrayStruct([
            'merchantId' => (string) $this->configService->getConfig('merchantId', $salesChannelId),
            'mode' => (string) $this->configService->getConfig('mode', $salesChannelId),
            'amount' => $amount,
            'currency' => (string) $currency,
            'enablePriceBreakdownProduct' => $enablePriceBreakdownProduct,
        ]));
    }
}