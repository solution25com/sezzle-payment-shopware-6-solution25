<?php

declare(strict_types=1);

namespace Sezzle\Storefront\Controller;

use Sezzle\Services\ConfigService;
use Sezzle\Services\ExpressCheckoutService;
use Sezzle\Services\SezzleCustomerTokenizationService;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ExpressCheckoutController extends StorefrontController
{
    public function __construct(
        private readonly ExpressCheckoutService $expressCheckoutService,
        private readonly SezzleCustomerTokenizationService $tokenizationService,
        private readonly CartService $cartService,
        private readonly ConfigService $configService,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        /** @phpstan-ignore-next-line reason: This only injects Shopware's core payment service. The v6.8 deprecation marks the class final; it does not remove this service usage. */
        private readonly PaymentProcessor $paymentProcessor
    ) {
    }

    #[Route(path: '/sezzle/express-checkout/check', name: 'frontend.sezzle.express_checkout.check', methods: ['POST'], defaults: ['XmlHttpRequest' => true])]
    public function checkExpressCheckout(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->isExpressCheckoutEnabled($context)) {
            return $this->disabledResponse();
        }

        $customer = $context->getCustomer();
        if (!$customer) {
            return new JsonResponse([
                'available' => false,
                'reason' => 'Not logged in',
            ]);
        }

        $tokenizedCustomer = $this->tokenizationService->getTokenizedCustomer(
            $customer->getId(),
            $context->getSalesChannelId(),
            $context->getContext()
        );
        if (!$tokenizedCustomer) {
            return new JsonResponse([
                'available' => false,
                'reason' => 'Customer not tokenized',
            ]);
        }

        return new JsonResponse([
            'available' => true,
            'sezzleCustomerUuid' => $tokenizedCustomer->getSezzleCustomerUuid(),
        ]);
    }

    #[Route(path: '/sezzle/express-checkout/create', name: 'frontend.sezzle.express_checkout.create', methods: ['POST'], defaults: ['XmlHttpRequest' => true])]
    public function createExpressCheckout(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->isExpressCheckoutEnabled($context)) {
            return $this->disabledResponse();
        }

        $customer = $context->getCustomer();
        if (!$customer) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Not logged in',
            ], 401);
        }

        $tokenizedCustomer = $this->tokenizationService->getTokenizedCustomer(
            $customer->getId(),
            $context->getSalesChannelId(),
            $context->getContext()
        );
        if (!$tokenizedCustomer) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Customer not tokenized',
            ], 400);
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $result = $this->expressCheckoutService->createExpressSession(
            $tokenizedCustomer->getSezzleCustomerUuid(),
            $cart,
            $context
        );
        $statusCode = $result['success'] ? 200 : 400;

        return new JsonResponse($result, $statusCode);
    }

    #[Route(path: '/sezzle/express/calculate-address-costs', name: 'frontend.sezzle.express_checkout.calculate_address_costs', methods: ['POST'], defaults: ['XmlHttpRequest' => true])]
    public function calculateAddressCosts(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->isExpressCheckoutEnabled($context)) {
            return $this->disabledResponse();
        }

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $result = $this->expressCheckoutService->updateShippingOptions(
            (string) ($payload['orderUuid'] ?? ''),
            (array) ($payload['shippingAddress'] ?? []),
            (array) ($payload['shippingOptions'] ?? []),
            $context
        );

        return new JsonResponse(
            ['ok' => (bool) ($result['success'] ?? false)],
            ($result['success'] ?? false) ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }

    #[Route(path: '/sezzle/express/finalize', name: 'frontend.sezzle.express_checkout.finalize', methods: ['POST'], defaults: ['XmlHttpRequest' => true])]
    public function finalize(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->isExpressCheckoutEnabled($context)) {
            return $this->disabledResponse();
        }

        $orderUuid = (string) $request->request->get('sezzleOrderUuid', '');
        $sessionToken = (string) $request->request->get('sezzleSessionToken', '');
        if ($orderUuid === '' || $sessionToken === '') {
            return new JsonResponse(['success' => false, 'error' => 'Missing Sezzle order data'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->expressCheckoutService->syncSelectedShippingFromSezzle($orderUuid, $context)['changed'] ?? false) {
            $context = $this->salesChannelContextService->get(
                new SalesChannelContextServiceParameters($context->getSalesChannelId(), $context->getToken())
            );
        }

        try {
            $cart = $this->cartService->getCart($context->getToken(), $context);
            $orderId = $this->cartService->order($cart, $context, new RequestDataBag([
                'sezzleOrderUuid' => $orderUuid,
                'sezzleSessionToken' => $sessionToken,
                'tos' => '1',
            ]));
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $finishUrl = $this->generateUrl('frontend.checkout.finish.page', ['orderId' => $orderId]);
        /** @phpstan-ignore-next-line reason: PaymentProcessor::pay remains Shopware's payment entry point; v6.8 deprecation only marks the class final. */
        $this->paymentProcessor->pay($orderId, $request, $context, $finishUrl, $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId]));

        return new JsonResponse(['success' => true, 'redirectUrl' => $finishUrl]);
    }

    private function isExpressCheckoutEnabled(SalesChannelContext $context): bool
    {
        return $this->configService->isLaunchFeatureEnabled(
            'expressCheckoutEnabled',
            $context->getSalesChannelId()
        );
    }

    private function disabledResponse(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'available' => false,
            'error' => 'Express checkout is disabled',
        ], Response::HTTP_NOT_FOUND);
    }
}
