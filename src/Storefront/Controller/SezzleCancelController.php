<?php
declare(strict_types=1);
namespace Sezzle\Storefront\Controller;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
#[Route(defaults: ['_routeScope' => ['storefront']])]
class SezzleCancelController extends StorefrontController
{
    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler
    ) {
    }
    #[Route(path: '/sezzle/cancel/{orderTransactionId}/{orderId}/{token}', name: 'frontend.sezzle.cancel', methods: ['GET'])]
    public function cancel(string $orderTransactionId, string $orderId, string $token, Context $context): RedirectResponse
    {
        $secret = getenv('APP_SECRET') ?: ($_ENV['APP_SECRET'] ?? '');
        if ($secret === '') {
            throw $this->createAccessDeniedException('Application secret not configured.');
        }
        $expectedToken = $orderTransactionId . '-' . hash_hmac('sha256', $orderId, $secret);
        if (!hash_equals($expectedToken, $token)) {
            throw $this->createAccessDeniedException('Invalid cancel token.');
        }
        $this->transactionStateHandler->fail($orderTransactionId, $context);
        return new RedirectResponse("/account/order/edit/{$orderId}?sezzleCancel=true");
    }
}
