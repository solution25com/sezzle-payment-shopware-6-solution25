<?php
declare(strict_types=1);
namespace Sezzle\Services;
use Sezzle\Services\SezzleClientService;
use Sezzle\Services\ConfigService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
class ExpressCheckoutService
{
    public function __construct(
        private readonly SezzleClientService $sezzleClientService,
        private readonly ConfigService $configService,
        private readonly SezzleCustomerTokenizationService $tokenizationService
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
    public function updateShippingOptions(string $orderUuid, array $shippingAddress): bool
    {
        $token = $this->sezzleClientService->authenticate();

        $shippingOptions = [
            [
                'name' => 'Standard Shipping',
                'description' => '3-5 business days',
                'shipping_amount_in_cents' => 1000,
                'tax_amount_in_cents' => 500,
                'final_order_amount_in_cents' => 11500,
            ],
        ];

        return $this->sezzleClientService->patchOrderCheckout(
            $orderUuid,
            $token,
            [
                'currency_code' => 'USD',
                'address_uuid' => $shippingAddress['uuid'],
                'shipping_options' => $shippingOptions,
            ]
        );
    }

    public function updateCheckoutWithCustomer(
        string $orderUuid,
        array $customerData,
        string $salesChannelId
    ): array {
        try {
            $response = $this->sezzleClientService->updateCheckout($orderUuid, $customerData, $salesChannelId);
            return [
                'success' => true,
                'data' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
