<?php

declare(strict_types=1);

namespace Sezzle\Subscriber;

use Sezzle\Services\ConfigService;
use Sezzle\Storefront\Struct\CheckoutTemplateCustomData;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCartPageLoaded',
        ];
    }

    public function onCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $enablePriceBreakdownCart = (bool) ($this->configService->getConfig('enablePriceBreakdownCart', $salesChannelId) ?? true);

        $page = $event->getPage();
        $cart = $page->getCart();

        if (!$cart) {
            return;
        }

        $amount = $cart->getPrice()->getTotalPrice();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();


        $templateVariables = new CheckoutTemplateCustomData();
        $templateVariables->assign([
            'merchantId' => $this->configService->getConfig('merchantId', $salesChannelId),
            'publicKey'  => $this->configService->getConfig('apiKeySandbox', $salesChannelId),
            'mode' => $this->configService->getConfig('mode', $salesChannelId),
            'amount' => $amount,
            'currency' => $currency,
            'enablePriceBreakdownCart' => $enablePriceBreakdownCart,


        ]);

        $event->getPage()->addExtension('sezzle', $templateVariables);
    }
}


