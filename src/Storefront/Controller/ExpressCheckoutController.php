<?php
declare(strict_types=1);
namespace Sezzle\Storefront\Controller;
use Sezzle\Services\ExpressCheckoutService;
use Sezzle\Services\SezzleCustomerTokenizationService;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route(defaults: ['_routeScope' => ['storefront']])]
class ExpressCheckoutController extends StorefrontController
{
    public function __construct(
        private readonly ExpressCheckoutService $expressCheckoutService,
        private readonly SezzleCustomerTokenizationService $tokenizationService,
        private readonly CartService $cartService
    ) {
    }
    #[Route(path: '/sezzle/express-checkout/check', name: 'frontend.sezzle.express_checkout.check', methods: ['POST'], defaults: ['XmlHttpRequest' => true])]
    public function checkExpressCheckout(Request $request, SalesChannelContext $context): JsonResponse
    {
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
}
