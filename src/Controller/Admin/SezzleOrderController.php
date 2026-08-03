<?php

declare(strict_types=1);

namespace Sezzle\Controller\Admin;

use Sezzle\Gateways\SezzlePaymentHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SezzleOrderController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository $orderRepository
    ) {
    }
    #[Route(path: '/api/sezzle/orders/{orderId}', name: 'api.sezzle.orders.detail', methods: ['GET'])]
    public function getOrderWithSezzleData(string $orderId, Context $context): JsonResponse
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return new JsonResponse([
                'error' => 'Order not found',
            ], 404);
        }
        $customFields = $order->getCustomFields() ?? [];
        $sezzleData = [
            'orderNumber' => $order->getOrderNumber(),
            'orderId' => $order->getId(),
            'amountTotal' => $order->getAmountTotal(),
            'currency' => $order->getCurrency()?->getIsoCode(),
            'sezzleOrderUuid' => $customFields['sezzleOrderUuid'] ?? null,
            'sezzleCheckoutUuid' => $customFields['sezzleCheckoutUuid'] ?? null,
            'sezzleSessionToken' => $customFields['sezzleSessionToken'] ?? null,
            'sezzleSessionUuid' => $customFields['sezzleSessionUuid'] ?? null,
            'sezzleStatus' => $customFields['sezzleStatus'] ?? null,
            'sezzleAmount' => $customFields['sezzleAmount'] ?? null,
            'sezzleCurrency' => $customFields['sezzleCurrency'] ?? null,
            'sezzleCaptureUuid' => $customFields['sezzleCaptureUuid'] ?? null,
            'sezzleRefundUuid' => $customFields['sezzleRefundUuid'] ?? null,
            'sezzleCreateSessionRequest' => $customFields['sezzleCreateSessionRequest'] ?? null,
            'sezzleCreateSessionResponse' => $customFields['sezzleCreateSessionResponse'] ?? null,
            'sezzleCreateOrderRequest' => $customFields['sezzleCreateOrderRequest'] ?? null,
            'sezzleCreateOrderResponse' => $customFields['sezzleCreateOrderResponse'] ?? null,
            'sezzleCaptureRequest' => $customFields['sezzleCaptureRequest'] ?? null,
            'sezzleCaptureResponse' => $customFields['sezzleCaptureResponse'] ?? null,
            'sezzleRefundRequest' => $customFields['sezzleRefundRequest'] ?? null,
            'sezzleRefundResponse' => $customFields['sezzleRefundResponse'] ?? null,
            'sezzleWebhookPayloads' => $customFields['sezzleWebhookPayloads'] ?? [],
        ];
        return new JsonResponse([
            'order' => [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'amountTotal' => $order->getAmountTotal(),
                'orderDateTime' => $order->getOrderDateTime()->format('Y-m-d H:i:s'),
            ],
            'sezzleData' => $sezzleData,
        ]);
    }
    #[Route(path: '/api/sezzle/transactions', name: 'api.sezzle.transactions.list', methods: ['GET'])]
    public function listSezzleTransactions(Request $request, Context $context): JsonResponse
    {
        $limit = (int) ($request->query->get('limit') ?? 25);
        $offset = (int) ($request->query->get('offset') ?? 0);
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        $currency = $request->query->get('currency');
        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('orderCustomer');
        $criteria->addFilter(new EqualsFilter('transactions.paymentMethod.handlerIdentifier', SezzlePaymentHandler::class));
        if ($startDate) {
            $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter(
                'orderDateTime',
                [\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter::GTE => $startDate]
            ));
        }
        if ($endDate) {
            $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter(
                'orderDateTime',
                [\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter::LTE => $endDate]
            ));
        }
        if ($currency) {
            $criteria->addFilter(new EqualsFilter('currency.isoCode', $currency));
        }
        $orders = $this->orderRepository->search($criteria, $context);
        $data = [];
        foreach ($orders as $order) {
            /** @var OrderEntity $order */
            $customFields = $order->getCustomFields() ?? [];
            $data[] = [
                'orderId' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'amountTotal' => $order->getAmountTotal(),
                'currency' => $order->getCurrency()?->getIsoCode(),
                'orderDateTime' => $order->getOrderDateTime()->format('Y-m-d H:i:s'),
                'customerEmail' => $order->getOrderCustomer()?->getEmail(),
                'sezzleOrderUuid' => $customFields['sezzleOrderUuid'] ?? null,
                'sezzleStatus' => $customFields['sezzleStatus'] ?? null,
            ];
        }
        return new JsonResponse([
            'data' => $data,
            'total' => $orders->getTotal(),
        ]);
    }
}
