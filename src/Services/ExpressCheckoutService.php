<?php

declare(strict_types=1);

namespace Sezzle\Services;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ExpressCheckoutService
{
    public function __construct(
        private readonly SezzleClientService $sezzleClientService,
        private readonly ConfigService $configService,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly AbstractContextSwitchRoute $contextSwitchRoute
    ) {
    }
    public function createExpressSession(
        string $sezzleCustomerUuid,
        Cart $cart,
        SalesChannelContext $salesChannelContext
    ): array {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $currency = $salesChannelContext->getCurrency();
        $currencyCode = $currency->getIsoCode();
        $totalAmount = $cart->getPrice()->getTotalPrice();
        $amountInCents = (int) round($totalAmount * 100);
        $orderItems = [];
        foreach ($cart->getLineItems() as $lineItem) {
            $lineItemTotal = $lineItem->getPrice()->getTotalPrice();
            $orderItems[] = [
                'name' => $lineItem->getLabel(),
                'sku' => $lineItem->getReferencedId() ?? $lineItem->getId(),
                'quantity' => $lineItem->getQuantity(),
                'price' => [
                    'amount_in_cents' => (int) round($lineItemTotal * 100),
                    'currency' => $currencyCode,
                ],
            ];
        }
        $authorizeAndCapture = $this->configService->getConfig('authorizeAndCapture', $salesChannelId) ?? 'auth';
        $intent = $authorizeAndCapture === 'direct_capture' ? 'CAPTURE' : 'AUTHORIZE';
        $orderData = [
            'customer_uuid' => $sezzleCustomerUuid,
            'intent' => $intent,
            'reference_id' => 'express-' . uniqid(),
            'order' => [
                'currency_code' => $currencyCode,
                'order_amount' => [
                    'amount_in_cents' => $amountInCents,
                    'currency' => $currencyCode,
                ],
                'order_items' => $orderItems,
            ],
        ];
        try {
            $response = $this->sezzleClientService->chargeCustomer($sezzleCustomerUuid, $orderData, $salesChannelId);
            return [
                'success' => true,
                'orderUuid' => $response['uuid'] ?? null,
                'checkoutUrl' => $response['checkout_url'] ?? null,
                'data' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function updateShippingOptions(
        string $orderUuid,
        array $shippingAddress,
        array $shippingOptions,
        SalesChannelContext $salesChannelContext
    ): array {
        $addressUuid = (string) ($shippingAddress['uuid'] ?? '');
        if ($addressUuid === '' || $shippingOptions === []) {
            return ['success' => false, 'error' => 'Missing address uuid or shipping options'];
        }

        $body = [
            'currency_code' => $salesChannelContext->getCurrency()->getIsoCode(),
            'address_uuid' => $addressUuid,
            'shipping_options' => $shippingOptions,
        ];

        try {
            $response = $this->sezzleClientService->updateCheckout(
                $orderUuid,
                $body,
                $salesChannelContext->getSalesChannelId()
            );
            return ['success' => true, 'data' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncSelectedShippingFromSezzle(
        string $orderUuid,
        SalesChannelContext $salesChannelContext
    ): array {
        try {
            $orderData = $this->sezzleClientService->getOrder(
                $orderUuid,
                $salesChannelContext->getSalesChannelId()
            );
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to fetch Sezzle order: ' . $e->getMessage()];
        }

        $selectedName = trim((string) ($orderData['shipping_method']['name'] ?? ''));
        if ($selectedName === '') {
            return ['success' => false, 'error' => 'No shipping selection found in Sezzle order'];
        }

        $shippingMethod = $this->findShippingMethodByName($selectedName, $salesChannelContext);
        if ($shippingMethod === null) {
            return ['success' => false, 'error' => 'No matching Shopware shipping method'];
        }

        if ($shippingMethod->getId() === $salesChannelContext->getShippingMethod()->getId()) {
            return ['success' => true, 'changed' => false];
        }

        $this->contextSwitchRoute->switchContext(
            new RequestDataBag([
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethod->getId(),
            ]),
            $salesChannelContext
        );

        return ['success' => true, 'changed' => true];
    }

    private function findShippingMethodByName(string $name, SalesChannelContext $salesChannelContext): ?ShippingMethodEntity
    {
        $cleanName = $this->stripPriceSuffix($name);
        $context = $salesChannelContext->getContext();
        $shippingMethodNames = array_values(array_unique([$name, $cleanName]));

        // Load all possible name matches at once to avoid repository calls inside the fallback loop.
        $matches = $this->shippingMethodRepository
            ->search((new Criteria())->addFilter(new EqualsAnyFilter('name', $shippingMethodNames)), $context)
            ->getEntities();

        foreach ($shippingMethodNames as $shippingMethodName) {
            foreach ($matches as $shippingMethod) {
                if (!$shippingMethod instanceof ShippingMethodEntity) {
                    continue;
                }

                if ($shippingMethod->getName() === $shippingMethodName && $shippingMethod->getActive()) {
                    return $shippingMethod;
                }
            }
        }

        return null;
    }

    private function stripPriceSuffix(string $name): string
    {
        return trim(preg_replace('/\s*-\s*\$[0-9.,]+\s*$/', '', $name) ?? $name);
    }
}
