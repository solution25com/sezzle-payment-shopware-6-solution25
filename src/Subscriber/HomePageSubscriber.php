<?php

declare(strict_types=1);

namespace Sezzle\Subscriber;

use Sezzle\Storefront\Struct\CheckoutTemplateCustomData;
use Sezzle\Services\ConfigService;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HomePageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CmsPageLoadedEvent::class => 'onCmsPageLoaded',
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded',
        ];
    }

    public function onHeaderLoaded(HeaderPageletLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $enableHomepageBanner = $this->configService->getConfig('enableHomepageBanner', $salesChannelId) ?? true;

        if (!$enableHomepageBanner) {
            return;
        }

        $templateVariables = new CheckoutTemplateCustomData();
        $templateVariables->assign([
            'merchantId' => $this->configService->getConfig('merchantId', $salesChannelId),
            'mode' => $this->configService->getConfig('mode', $salesChannelId),
            'enableHomepageBanner' => true,
        ]);

        $event->getPagelet()->addExtension(
            CheckoutTemplateCustomData::HEADER_EXTENSION,
            $templateVariables
        );
    }

    public function onCmsPageLoaded(CmsPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $enableHomepageBanner = $this->configService->getConfig('enableHomepageBanner', $salesChannelId) ?? true;
        
        if (!$enableHomepageBanner) {
            return;
        }

//        $page = $event->getPage();
        $templateVariables = new CheckoutTemplateCustomData();
        $templateVariables->assign([
            'merchantId' => $this->configService->getConfig('merchantId', $salesChannelId),
            'mode' => $this->configService->getConfig('mode', $salesChannelId),
            'enableHomepageBanner' => true,
        ]);


        $event->getResult()->addExtension(
            CheckoutTemplateCustomData::EXTENSION_NAME,
            $templateVariables);
    }
}

