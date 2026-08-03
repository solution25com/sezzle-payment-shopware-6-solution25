<?php

declare(strict_types=1);

namespace Sezzle\Storefront\Controller;

use Sezzle\Services\ConfigService;
use Sezzle\Services\OrderTransactionMapper\OrderTransactionMapper;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class SezzlePopupController extends StorefrontController
{
    public function __construct(
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly ConfigService $configService
    ) {
    }

    #[Route(path: '/sezzle/popup-start/{transactionId}', name: 'frontend.sezzle.popup_start', methods: ['GET'])]
    public function popupStart(string $transactionId, SalesChannelContext $context): Response
    {
        $orderTransaction = $this->orderTransactionMapper->getOrderTransactionsById(
            $transactionId,
            $context->getContext()
        );

        if ($orderTransaction === null) {
            return $this->redirectToRoute('frontend.home.page');
        }

        $order = $orderTransaction->getOrder();
        $customFields = $order->getCustomFields() ?? [];

        $checkoutUrl = $customFields['sezzleCheckoutUrl'] ?? '';
        $returnUrl = $customFields['sezzleReturnUrl'] ?? '';

        if ($checkoutUrl === '' || $returnUrl === '') {
            return $this->redirectToRoute('frontend.home.page');
        }

        $salesChannelId = $context->getSalesChannelId();
        $apiMode = $this->configService->getConfig('mode', $salesChannelId) ?? 'sandbox';
        $popupFormStyle = $this->configService->getConfig('popupFormStyle', $salesChannelId) ?? 'popup';
        $isProd = $apiMode === 'live';
        $publicKey = $isProd
            ? (string) ($this->configService->getConfig('apiKeyLive', $salesChannelId) ?? '')
            : (string) ($this->configService->getConfig('apiKeySandbox', $salesChannelId) ?? '');

        return $this->renderStorefront('@Sezzle/storefront/page/checkout/sezzle-popup.html.twig', [
            'checkoutUrl' => $checkoutUrl,
            'returnUrl' => $returnUrl,
            'mode' => $popupFormStyle,
            'publicKey' => $publicKey,
            'apiMode' => $apiMode,
        ]);
    }
}
