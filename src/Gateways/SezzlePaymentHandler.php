<?php

declare(strict_types=1);

namespace Sezzle\Gateways;

use Sezzle\Event\SezzlePaymentCompletedEvent;
use Sezzle\Exception\SezzleApiException;
use Sezzle\Exception\SezzleAuthException;
use Sezzle\Library\Constants\SezzlePaymentStatus;
use Sezzle\Services\ConfigService;
use Sezzle\Services\Endpoints;
use Sezzle\Services\OrderTransactionMapper\OrderTransactionMapper;
use Sezzle\Services\SezzleClientService;
use Sezzle\Services\SezzleOrderPaymentStatusResolver;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SezzlePaymentHandler extends AbstractPaymentHandler
{
    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly SezzleClientService $sezzleClientService,
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly ConfigService $configService,
        private readonly SezzleOrderPaymentStatusResolver $paymentStatusResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return true;
    }

    public function validate(Cart $cart, RequestDataBag $dataBag, SalesChannelContext $context): ?Struct
    {
        $popupFormStyle = $this->configService->getConfig('popupFormStyle', $context->getSalesChannelId()) ?? 'redirect';

        if ($popupFormStyle !== 'popup') {
            return null;
        }

        if (!$dataBag->get('sezzleOrderUuid') || !$dataBag->get('sezzleSessionToken')) {
            throw new SezzleApiException('Please complete the Sezzle popup before placing the order.');
        }

        return null;
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
        $intent = $this->paymentStatusResolver->resolvePaymentIntent($salesChannelId);
        $popupFormStyle = $this->configService->getConfig('popupFormStyle', $salesChannelId) ?? 'redirect';
        $tokenize = $this->configService->isLaunchFeatureEnabled('enableTokenization', $salesChannelId);

        if ($popupFormStyle === 'popup') {
            try {
                $popupOrderUuid = $request->request->get('sezzleOrderUuid');
                $popupSessionToken = $request->request->get('sezzleSessionToken');
                $popupSessionUuid = $request->request->get('sezzleSessionUuid', $popupSessionToken);
                $popupCheckoutUuid = $request->request->get('sezzleCheckoutUuid');

                if (!$popupOrderUuid || !$popupSessionToken) {
                    throw new SezzleApiException('Sezzle popup checkout did not return the required values.');
                }

                $sessionDetails = $this->sezzleClientService->getSession($popupSessionToken, $salesChannelId);
                $verifiedOrderUuid = $sessionDetails['order']['uuid'] ?? null;

                if (!$verifiedOrderUuid || $verifiedOrderUuid !== $popupOrderUuid) {
                    throw new SezzleApiException('Unable to verify Sezzle popup order.');
                }

                $popupSessionRequest = [
                    'checkout_payload' => [
                        'order' => [
                            'intent' => $intent,
                            'reference_id' => 'cart-' . $order->getOrderNumber(),
                            'description' => 'Order from Shopware',
                            'order_amount' => [
                                'amount_in_cents' => $amountInCents,
                                'currency' => $currencyCode,
                            ],
                        ],
                    ],
                ];

                $this->orderTransactionMapper->setSezzleCustomFieldFromOrder(
                    $order,
                    $context,
                    [
                        'sezzleOrderUuid' => $verifiedOrderUuid,
                        'sezzleSessionToken' => $popupSessionToken,
                        'sezzleSessionUuid' => $sessionDetails['uuid'] ?? $popupSessionUuid,
                        'sezzleCheckoutUuid' => $sessionDetails['checkout']['uuid'] ?? $popupCheckoutUuid,
                        'sezzleSessionDetails' => json_encode($sessionDetails),
                        'sezzleCreateSessionRequest' => json_encode($popupSessionRequest),
                        'sezzleCreateSessionResponse' => json_encode($sessionDetails),
                        'sezzleWebhookPayloads' => [],
                        'sezzleProcessedWebhookEvents' => [],
                        'sezzlePaymentIntent' => $intent,
                    ]
                );

                $status = $this->paymentStatusResolver->fetchAndResolve($verifiedOrderUuid, $salesChannelId);

                if ($status !== SezzlePaymentStatus::PENDING) {
                    $this->paymentStatusResolver->applyToTransaction($status, $transactionId, $salesChannelId, $context);
                }

                if ($status === SezzlePaymentStatus::DECLINED) {
                    throw new \RuntimeException('Sezzle payment was not approved');
                }

                // Fires for either VERIFIED_AUTHORIZED or VERIFIED_CAPTURED — whichever
                // the merchant's Authorize/Capture config produces — so CORE order sync
                // triggers the same way regardless of that setting.
                if ($status !== SezzlePaymentStatus::PENDING) {
                    $this->eventDispatcher->dispatch(new SezzlePaymentCompletedEvent($order->getId(), $transactionId, $context));
                }

                return null;
            } catch (\Exception $e) {
                $this->transactionStateHandler->fail($transactionId, $context);
                throw $e;
            }
        }

        $sessionBody = [
            'cancel_url' => [
                'href' => $returnUrlOnCancel,
                'method' => 'GET',
            ],
            'complete_url' => [
                'href' => $transaction->getReturnUrl(),
                'method' => 'GET',
            ],
            'customer' => [
                'email' => $orderCustomer->getEmail(),
                'first_name' => $billingAddress->getFirstName(),
                'last_name' => $billingAddress->getLastName(),
                'phone' => $billingAddress->getPhoneNumber(),
                'dob' => $customer->getBirthday()?->format('Y-m-d'),
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

        if ($tokenize) {
            $sessionBody['customer']['tokenize'] = true;
        }

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
                    'sezzleProcessedWebhookEvents' => [],
                    'sezzlePaymentIntent' => $intent,
                ]
            );

            $checkoutUrl = $sessionResponse['order']['checkout_url'] ?? null;
            if (!is_string($checkoutUrl) || $checkoutUrl === '') {
                $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
                throw new SezzleApiException('Sezzle checkout URL missing from session response.');
            }

            return new RedirectResponse($checkoutUrl);
        } catch (SezzleAuthException | SezzleApiException $e) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw $e;
        }
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $transactionId = $transaction->getOrderTransactionId();

        try {
            $orderTransaction = $this->orderTransactionMapper->getOrderTransactionsById($transactionId, $context);
            $order = $orderTransaction->getOrder();
            $salesChannelId = $order->getSalesChannelId();
            $customFields = $order->getCustomFields() ?? [];
            $sezzleOrderUuid = $customFields['sezzleOrderUuid'] ?? null;

            if (!$sezzleOrderUuid) {
                $this->transactionStateHandler->fail($transactionId, $context);
                throw new \RuntimeException('Sezzle order UUID not found');
            }

            $sessionToken = $customFields['sezzleSessionToken'] ?? null;
            if ($sessionToken) {
                try {
                    $sessionDetails = $this->sezzleClientService->getSession($sessionToken, $salesChannelId);
                    $this->orderTransactionMapper->setSezzleCustomFieldFromOrder(
                        $order,
                        $context,
                        array_merge($customFields, [
                            'sezzleCustomerUuid' => $sessionDetails['uuid'] ?? null,
                            'sezzleCustomerTokenized' => $sessionDetails['tokenized']['token'] ?? false,
                            'sezzleSessionDetails' => json_encode($sessionDetails),
                        ])
                    );
                } catch (\Exception) {
                }
            }

            $status = $this->paymentStatusResolver->fetchAndResolve($sezzleOrderUuid, $salesChannelId);

            if ($status === SezzlePaymentStatus::PENDING) {
                return;
            }

            $this->paymentStatusResolver->applyToTransaction($status, $transactionId, $salesChannelId, $context);

            if ($status === SezzlePaymentStatus::DECLINED) {
                throw new \RuntimeException('Sezzle payment was not approved');
            }

            // Past this point status is VERIFIED_AUTHORIZED or VERIFIED_CAPTURED —
            // sezzleOrderUuid/session/charge custom fields are now written, so CORE
            // order sync (listening for this event) has what it needs to map the order.
            $this->eventDispatcher->dispatch(new SezzlePaymentCompletedEvent($order->getId(), $transactionId, $context));
        } catch (SezzleAuthException | SezzleApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->transactionStateHandler->fail($transactionId, $context);
            throw $e;
        }
    }
}
