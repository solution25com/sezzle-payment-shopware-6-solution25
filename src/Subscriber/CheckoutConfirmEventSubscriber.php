<?php

declare(strict_types=1);

namespace Sezzle\Subscriber;

use Sezzle\Gateways\SezzlePaymentHandler;
use Sezzle\Services\ConfigService;
use Sezzle\Storefront\Struct\CheckoutTemplateCustomData;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields',
        ];
    }

    public function addPaymentMethodSpecificFormFields(CheckoutConfirmPageLoadedEvent $event): void
    {
        $this->filterSezzlePaymentMethod($event);

        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $mode = $this->configService->getConfig('mode', $salesChannelId);
        $merchantId = $this->configService->getConfig('merchantId', $salesChannelId);
        $popupFormStyle = $this->configService->getConfig('popupFormStyle', $salesChannelId) ?? 'redirect';
        $enablePromotionalWidget = $this->configService->isLaunchFeatureEnabled('enablePromotionalWidget', $salesChannelId);
        $authorizeAndCapture = $this->configService->getConfig('authorizeAndCapture', $salesChannelId) ?? 'auth';
        $isLive = $mode === 'live';
        $publicKey = $isLive
            ? $this->configService->getConfig('apiKeyLive', $salesChannelId)
            : $this->configService->getConfig('apiKeySandbox', $salesChannelId);
        $expressCheckout = $this->configService->isLaunchFeatureEnabled('expressCheckoutEnabled', $salesChannelId);

        $pageObject = $event->getPage();
        $cartAmount = $pageObject->getCart()->getPrice()->getTotalPrice();
        $templateVariables = new CheckoutTemplateCustomData();
        $templateVariables->assign([
            'merchantId' => $merchantId,
            'mode' => $mode,
            'popupFormStyle' => $popupFormStyle,
            'enablePromotionalWidget' => $enablePromotionalWidget,
            'amount' => $cartAmount,
            'amountInCents' => (int) round($cartAmount * 100),
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'publicKey' => $publicKey,
            'expressCheckout' => $expressCheckout,
            'intent' => $authorizeAndCapture === 'direct_capture' ? 'CAPTURE' : 'AUTH',
            'referenceId' => 'cart-' . $salesChannelContext->getToken(),
            'description' => 'Order from Shopware',
        ]);
        $pageObject->addExtension(CheckoutTemplateCustomData::EXTENSION_NAME, $templateVariables);
    }

    private function filterSezzlePaymentMethod(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $pageObject = $event->getPage();
        $cartAmount = $pageObject->getCart()->getPrice()->getTotalPrice();
        $minAmount = (float) ($this->configService->getConfig('minAmount', $salesChannelId) ?? 0);
        $maxAmount = (float) ($this->configService->getConfig('maxAmount', $salesChannelId) ?? PHP_FLOAT_MAX);

        $filteredPaymentMethods = $pageObject->getPaymentMethods()->filter(
            function ($paymentMethod) use ($cartAmount, $minAmount, $maxAmount) {
                if ($paymentMethod->getHandlerIdentifier() === SezzlePaymentHandler::class) {
                    return $cartAmount >= $minAmount && $cartAmount <= $maxAmount;
                }

                return true;
            }
        );

        $pageObject->setPaymentMethods($filteredPaymentMethods);

        $currentPaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();
        if ($filteredPaymentMethods->has($currentPaymentMethodId)) {
            return;
        }

        $fallback = $filteredPaymentMethods->first();
        if ($fallback) {
            $salesChannelContext->assign([
                'paymentMethod' => $fallback,
            ]);
        }
    }
}
