<?php

declare(strict_types=1);

namespace Sezzle\Services;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Sezzle\DataAbstractionLayer\Entity\SezzleCustomer\SezzleCustomerEntity;
use Sezzle\Services\OrderTransactionMapper\OrderTransactionMapper;

class AdminOrderCreationService
{
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly CartService $cartService,
        private readonly OrderPersister $orderPersister,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly SezzleClientService $sezzleClientService,
        private readonly ConfigService $configService,
        private readonly OrderTransactionMapper $orderTransactionMapper,
    ) {
    }
    public function createOrderAndCharge(
        SezzleCustomerEntity $sezzleCustomer,
        array $orderData,
        Context $context
    ): array {
        $salesChannelId = $orderData['salesChannelId'] ?? throw new \InvalidArgumentException('salesChannelId is required');
        $products = $orderData['products'] ?? throw new \InvalidArgumentException('products array is required');
        if (empty($products)) {
            throw new \InvalidArgumentException('At least one product is required');
        }
        $shopwareCustomerId = $orderData['shopwareCustomerId'] ?? $sezzleCustomer->getShopwareCustomerId();
        if (!$shopwareCustomerId) {
            throw new \InvalidArgumentException('No Shopware customer linked to this Sezzle customer');
        }
        $sezzlePaymentMethodId = $this->getSezzlePaymentMethodId($salesChannelId, $context);
        if (!$sezzlePaymentMethodId) {
            throw new \RuntimeException('Sezzle payment method not found or not active in this sales channel');
        }
        $salesChannelContext = $this->createSalesChannelContext(
            $salesChannelId,
            $shopwareCustomerId,
            $sezzlePaymentMethodId,
            $context
        );
        $cart = $this->buildCart($products, $salesChannelContext, $context);
        if ($cart->getLineItems()->count() === 0) {
            $errorMessages = [];
            foreach ($cart->getErrors() as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new \RuntimeException(
                'Cart is empty after adding products. Errors: ' .
                (empty($errorMessages) ? 'None' : implode(', ', $errorMessages))
            );
        }
        if ($cart->getErrors()->blockOrder()) {
            throw CartException::invalidCart($cart->getErrors());
        }
        $orderId = $this->orderPersister->persist($cart, $salesChannelContext);
        $chargeResult = $this->chargeSezzleCustomer(
            $sezzleCustomer->getSezzleCustomerUuid(),
            $cart,
            $orderId,
            $salesChannelContext,
            $salesChannelId,
            $context
        );
        if (!$chargeResult['success']) {
            return [
                'success' => false,
                'orderId' => $orderId,
                'orderCreated' => true,
                'chargeSuccess' => false,
                'error' => $chargeResult['error'] ?? 'Failed to charge Sezzle customer',
                'sezzleResponse' => $chargeResult['response'] ?? null,
            ];
        }
        $this->storeChargeDataInOrder($orderId, $chargeResult['response'], $context);
        return [
            'success' => true,
            'orderId' => $orderId,
            'orderCreated' => true,
            'chargeSuccess' => true,
            'sezzleOrderUuid' => $chargeResult['response']['uuid'] ?? null,
            'sezzleResponse' => $chargeResult['response'],
        ];
    }
    private function buildCart(array $products, SalesChannelContext $salesChannelContext, Context $context): Cart
    {
        $token = 'admin-order-' . Uuid::randomHex();
        $productIds = array_column($products, 'productId');
        if (empty($productIds)) {
            throw new \RuntimeException("No products provided");
        }
        $criteria = new Criteria($productIds);
        $criteria->addAssociation('cover');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('prices');
        $criteria->addAssociation('tax');
        $productEntities = $this->productRepository->search($criteria, $context);
        if ($productEntities->count() === 0) {
            throw new \RuntimeException("No products found for IDs: " . implode(', ', $productIds));
        }
        $lineItems = [];
        foreach ($products as $productData) {
            $productId = $productData['productId'];
            $quantity = (int) ($productData['quantity'] ?? 1);
            /** @var ProductEntity|null $product */
            $product = $productEntities->get($productId);
            if (!$product) {
                throw new \RuntimeException("Product {$productId} not found");
            }
            if (!$product->getActive()) {
                throw new \RuntimeException("Product {$productId} is not active");
            }
            $lineItemId = Uuid::randomHex();
            $lineItem = new LineItem(
                $lineItemId,
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                $productId,
                $quantity
            );
            $lineItem->setStackable(true);
            $lineItem->setRemovable(true);
            $lineItem->setLabel($product->getName());
            $lineItem->setGood(true);
            $lineItems[] = $lineItem;
        }
        if (empty($lineItems)) {
            throw new \RuntimeException("No valid line items created");
        }
        $cart = new Cart($token);
        try {
            $enrichedCart = $this->cartService->add($cart, $lineItems, $salesChannelContext);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to add items to cart: " . $e->getMessage()
            );
        }
        if ($enrichedCart->getLineItems()->count() === 0) {
            $errorDetails = [];
            foreach ($enrichedCart->getErrors() as $error) {
                $errorDetails[] = $error->getMessage();
            }
            throw new \RuntimeException(
                "Cart is empty after adding products. " .
                (empty($errorDetails) ? "No error messages available. Products may be out of stock or unavailable." : "Errors: " . implode(', ', $errorDetails))
            );
        }
        return $enrichedCart;
    }
    private function createSalesChannelContext(
        string $salesChannelId,
        string $customerId,
        string $paymentMethodId,
        Context $context
    ): SalesChannelContext {
        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
        ];
        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $options
        );
    }
    private function getSezzlePaymentMethodId(string $salesChannelId, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('handlerIdentifier', \Sezzle\Gateways\SezzlePaymentHandler::class)
        );
        $criteria->addFilter(
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('active', true)
        );
        /** @var \Shopware\Core\Checkout\Payment\PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->first();
        return $paymentMethod?->getId();
    }
    private function chargeSezzleCustomer(
        string $sezzleCustomerUuid,
        Cart $cart,
        string $orderId,
        SalesChannelContext $salesChannelContext,
        string $salesChannelId,
        Context $context
    ): array {
        $currencyCode = $salesChannelContext->getCurrency()->getIsoCode();
        $totalAmount = $cart->getPrice()->getTotalPrice();
        $amountInCents = (int) round($totalAmount * 100);
        $orderItems = [];
        foreach ($cart->getLineItems() as $lineItem) {
            $lineItemTotal = $lineItem->getPrice()?->getTotalPrice() ?? 0;
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
        $chargeData = [
            'intent' => $intent,
            'reference_id' => $orderId,
            'currency_code' => $currencyCode,
            'description' => "Admin Order #{$orderId}",
            'order_amount' => [
                'amount_in_cents' => $amountInCents,
                'currency' => $currencyCode,
            ],
            'items' => $orderItems,
        ];
        try {
            $response = $this->sezzleClientService->chargeCustomer($sezzleCustomerUuid, $chargeData, $salesChannelId);
            return [
                'success' => true,
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => null,
            ];
        }
    }
    private function storeChargeDataInOrder(string $orderId, array $sezzleResponse, Context $context): void
    {
        $this->orderTransactionMapper->storeSezzleChargeResponse($orderId, $sezzleResponse, $context);
    }
}
