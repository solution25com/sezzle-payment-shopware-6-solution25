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
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $selectedPaymentGateway = $salesChannelContext->getPaymentMethod();
        if ($selectedPaymentGateway->getHandlerIdentifier() !== SezzlePaymentHandler::class) {
            return;
        }
        $mode = $this->configService->getConfig('mode', $salesChannelId);
        $merchantId = $this->configService->getConfig('merchantId', $salesChannelId);
        $popupFormStyle = $this->configService->getConfig('popupFormStyle', $salesChannelId) ?? 'popup';
        $enablePromotionalWidget = $this->configService->getConfig('enablePromotionalWidget', $salesChannelId) ?? true;
        $paymentFlow = $this->configService->getConfig('flow', $salesChannelId);
        $publicKey = $this->configService->getConfig('apiKeySandbox', $salesChannelId);
        $expressCheckout = $this->configService->getConfig('expressCheckoutEnabled', $salesChannelId);
        $privateKey = $this->configService->getConfig('apiPasswordSandbox', $salesChannelId);


        $pageObject = $event->getPage();
        $cartAmount = $pageObject->getCart()->getPrice()->getTotalPrice();
        $templateVariables = new CheckoutTemplateCustomData();
        $templateVariables->assign([
            'merchantId' => $merchantId,
            'mode' => $mode,
            'popupFormStyle' => $popupFormStyle,
            'enablePromotionalWidget' => $enablePromotionalWidget,
            'amount' => $cartAmount,
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'flow' => $paymentFlow,
            'publicKey' => $publicKey,
            'expressCheckout' => $expressCheckout,
            'privateKey' => $privateKey

        ]);
        $pageObject->addExtension('sezzle', $templateVariables);
    }
}
