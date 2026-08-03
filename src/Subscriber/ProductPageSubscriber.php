<?php

declare(strict_types=1);

namespace Sezzle\Subscriber;

use Sezzle\Services\ConfigService;
use Sezzle\Storefront\Struct\CheckoutTemplateCustomData;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configService
    ) {
    }

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

        $enablePriceBreakdownProduct = $this->configService->isLaunchFeatureEnabled(
            'enablePriceBreakdownProduct',
            $salesChannelId
        );

        if (!$enablePriceBreakdownProduct) {
            return;
        }

        $templateVariables = new CheckoutTemplateCustomData();
        $templateVariables->assign([
            'merchantId' => (string) $this->configService->getConfig('merchantId', $salesChannelId),
            'mode' => (string) $this->configService->getConfig('mode', $salesChannelId),
            'enablePriceBreakdownProduct' => $enablePriceBreakdownProduct,
        ]);

        $event->getPage()->addExtension('sezzle', $templateVariables);
    }
}
