<?php
declare(strict_types=1);
namespace Sezzle\Gateways;
use Sezzle\Exception\SezzleApiException;
use Sezzle\Exception\SezzleAuthException;
use Sezzle\Services\ConfigService;
use Sezzle\Services\Endpoints;
use Sezzle\Services\OrderTransactionMapper\OrderTransactionMapper;
use Sezzle\Services\SezzleClientService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
class SezzlePaymentHandler extends AbstractPaymentHandler
{
    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly SezzleClientService $sezzleClientService,
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly ConfigService $configService
    ) {
    }
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return true;
    }
    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        $salesChannelId = $request->attributes->get('sw-sales-channel-id');
        $order = $this->orderTransactionMapper->getOrderTransactionsById($transaction->getOrderTransactionId(), $context)->getOrder();
        $callbackUrl = Endpoints::callbackUrl($request->getSchemeAndHttpHost());
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress() ?? $billingAddress;
        $currency = $order->getCurrency();
        $currencyCode = $currency ? $order->getCurrency()->getIsoCode() : 'USD';
        $orderCustomer = $order->getOrderCustomer();
        $customer = $orderCustomer->getCustomer();
        $totalAmount = $order->getAmountTotal();
        $amountInCents = (int) round($totalAmount * 100);
        $transactionId = $transaction->getOrderTransactionId();
        $customToken = $transactionId . '-' . hash_hmac('sha256', $order->getId(), (getenv('APP_SECRET') ?: ($_ENV['APP_SECRET'] ?? '')));
        $returnUrlOnCancel = sprintf(
            '%s/sezzle/cancel/%s/%s/%s',
            $request->getSchemeAndHttpHost(),
            $transactionId,
            $order->getId(),
            $customToken
        );
        $popupFormStyle = 'redirect';
        $authorizeAndCapture = $this->configService->getConfig('authorizeAndCapture', $salesChannelId) ?? 'auth';
        $intent = $authorizeAndCapture === 'direct_capture' ? 'CAPTURE' : 'CAPTURE';
        $sessionBody = [
            'cancel_url' => [
                'href' => $returnUrlOnCancel,
                'method' => 'GET'
            ],
            'complete_url' => [
                'href' => $transaction->getReturnUrl(),
                'method' => 'GET'
            ],
            'customer' => [
                'email' => $orderCustomer->getEmail(),
                'first_name' => $billingAddress->getFirstName(),
                'last_name' => $billingAddress->getLastName(),
                'phone' => $billingAddress->getPhoneNumber(),
                'dob' => $customer->getBirthday()?->format('Y-m-d'),
                'tokenize' => true,
                'billing_address' => [
                    'name' => $billingAddress->getFirstName() . ' ' . $billingAddress->getLastName(),
                    'street' => $billingAddress->getStreet(),
                    'street2' => '',
                    'city' => $billingAddress->getCity(),
                    'state' => $billingAddress->getCountryState()?->getShortCode() ?? '',
                    'postal_code' => $billingAddress->getZipCode(),
                    'country_code' => $billingAddress->getCountry()?->getIso() ?? 'US',
                ],
                'shipping_address' => [
                    'name' => $shippingAddress->getFirstName() . ' ' . $shippingAddress->getLastName(),
                    'street' => $shippingAddress->getStreet(),
                    'street2' => '',
                    'city' => $shippingAddress->getCity(),
                    'state' => $shippingAddress->getCountryState()?->getShortCode() ?? '',
                    'postal_code' => $shippingAddress->getZipCode(),
                    'country_code' => $shippingAddress->getCountry()?->getIso() ?? 'US',
                ],
            ],
            'order' => [
                'intent' => $intent,
                'reference_id' => $order->getOrderNumber(),
                'currency_code' => $currencyCode,
                'description' => 'Order #' . $order->getOrderNumber(),
                'order_amount' => [
                    'amount_in_cents' => $amountInCents,
                    'currency' => $currencyCode,
                ],
                'items' => [],
            ],
        ];
        foreach ($order->getLineItems() as $lineItem) {
            $sessionBody['order']['items'][] = [
                'name' => $lineItem->getLabel(),
                'sku' => $lineItem->getPayload()['productNumber'] ?? $lineItem->getId(),
                'quantity' => $lineItem->getQuantity(),
                'price' => [
                    'amount_in_cents' => (int) round($lineItem->getTotalPrice() * 100),
                    'currency' => $currencyCode,
                ],
            ];
        }
        try {
            $sessionResponse = $this->sezzleClientService->createSession($sessionBody, $salesChannelId);
            if (empty($sessionResponse['uuid']) || empty($sessionResponse['order']['uuid'])) {
                $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
                throw new SezzleApiException('Unable to create Sezzle session. Please verify your Sezzle configuration.');
            }
            $this->orderTransactionMapper->setSezzleCustomFieldFromOrder(
                $order,
                $context,
                [
                    'sezzleOrderUuid' => $sessionResponse['order']['uuid'],
                    'sezzleSessionToken' => $sessionResponse['uuid'],
                    'sezzleCheckoutUuid' => $sessionResponse['checkout']['uuid'] ?? null,
                    'sezzleSessionUuid' => $sessionResponse['uuid'],
                    'sezzleCreateSessionRequest' => json_encode($sessionBody),
                    'sezzleCreateSessionResponse' => json_encode($sessionResponse),
                    'sezzleWebhookPayloads' => [],
                ]
            );
            if ($popupFormStyle === 'redirect' && isset($sessionResponse['order']['checkout_url'])) {
              return new RedirectResponse($sessionResponse['order']['checkout_url']);
            } else {
                $checkoutUrl = $transaction->getReturnUrl() . '?sezzle_session_token=' . urlencode($sessionResponse['uuid']);
              return new RedirectResponse($checkoutUrl);
            }
        } catch (SezzleAuthException | SezzleApiException $e) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw $e;
        }
    }
    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        try {
            $salesChannelId = $request->attributes->get('sw-sales-channel-id');
            $orderTransaction = $this->orderTransactionMapper->getOrderTransactionsById($transaction->getOrderTransactionId(), $context);
            $order = $orderTransaction->getOrder();
            $customFields = $order->getCustomFields() ?? [];
            $sessionToken = $customFields['sezzleSessionToken'] ?? null;
            $sezzleOrderUuid = $customFields['sezzleOrderUuid'] ?? null;

            if (!$sessionToken || !$sezzleOrderUuid) {
                if ($sezzleOrderUuid) {
                    $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
                    return;
                }
                $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
                throw new \RuntimeException('Sezzle session token or order UUID not found');
            }
            try {
                $sessionDetails = $this->sezzleClientService->getSession($sessionToken, $order->getSalesChannelId());
                $customerUuid = $sessionDetails['uuid'] ?? null;
                $customerTokenized = $sessionDetails['tokenized']['token'] ?? false;
                $sezzleData = [
                    'sezzleCustomerUuid' => $customerUuid,
                    'sezzleCustomerTokenized' => $customerTokenized,
                    'sezzleSessionDetails' => json_encode($sessionDetails),
                ];
                $this->orderTransactionMapper->setSezzleCustomFieldFromOrder(
                    $order,
                    $context,
                    array_merge($customFields, $sezzleData)
                );
            } catch (\Exception $e) {
            }
            $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
        } catch (\Exception $e) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw $e;
        }
    }
}
