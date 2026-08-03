<?php

declare(strict_types=1);

namespace Sezzle\Controller\Admin;

use Sezzle\Services\AdminOrderCreationService;
use Sezzle\Services\SezzleCustomerService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SezzleOrderCreationController extends AbstractController
{
    public function __construct(
        private readonly AdminOrderCreationService $adminOrderCreationService,
        private readonly SezzleCustomerService $sezzleCustomerService,
    ) {
    }
    #[Route(
        path: '/api/_action/sezzle/customers/{customerId}/create-order',
        name: 'api.action.sezzle.customers.create_order',
        methods: ['POST']
    )]
    public function createOrderAndCharge(string $customerId, Request $request, Context $context): JsonResponse
    {
        try {
            $orderData = $request->request->all();
            if (!isset($orderData['products']) || empty($orderData['products'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Products array is required',
                ], 400);
            }
            if (!isset($orderData['salesChannelId'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Sales channel ID is required',
                ], 400);
            }
            $sezzleCustomer = $this->sezzleCustomerService->getCustomerByUuid($customerId, $context);
            if (!$sezzleCustomer) {
                $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$customerId]);
                $sezzleCustomer = $this->container->get('sezzle_customer.repository')->search($criteria, $context)->first();
            }
            if (!$sezzleCustomer) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Sezzle customer not found',
                ], 404);
            }
            if (!$sezzleCustomer->isTokenized()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Customer is not tokenized. Only tokenized customers can be charged.',
                ], 400);
            }
            $result = $this->adminOrderCreationService->createOrderAndCharge(
                $sezzleCustomer,
                $orderData,
                $context
            );
            $statusCode = $result['success'] ? 200 : 400;
            return new JsonResponse($result, $statusCode);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    #[Route(
        path: '/api/_action/sezzle/products/search',
        name: 'api.action.sezzle.products.search',
        methods: ['GET']
    )]
    public function searchProducts(Request $request, Context $context): JsonResponse
    {
        $term = $request->query->get('term', '');
        $limit = (int) ($request->query->get('limit') ?? 25);
        $salesChannelId = $request->query->get('salesChannelId');
        try {
            $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
            $criteria->setLimit($limit);
            $criteria->addAssociation('cover');
            $criteria->addFilter(
                new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('active', true)
            );
            if (!empty($term)) {
                $criteria->addFilter(
                    new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter(
                        \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter::CONNECTION_OR,
                        [
                            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter('name', $term),
                            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter('productNumber', $term),
                        ]
                    )
                );
            }
            $productRepository = $this->container->get('product.repository');
            $products = $productRepository->search($criteria, $context);
            $data = [];
            foreach ($products as $product) {
                $data[] = [
                    'id' => $product->getId(),
                    'name' => $product->getTranslation('name'),
                    'productNumber' => $product->getProductNumber(),
                    'price' => $product->getPrice() ? $product->getPrice()->first()?->getGross() : 0,
                    'stock' => $product->getStock(),
                ];
            }
            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'total' => $products->getTotal(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
