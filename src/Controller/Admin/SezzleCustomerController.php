<?php
declare(strict_types=1);
namespace Sezzle\Controller\Admin;
use Sezzle\Services\SezzleCustomerService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route(defaults: ['_routeScope' => ['api']])]
class SezzleCustomerController extends AbstractController
{
    public function __construct(
        private readonly SezzleCustomerService $sezzleCustomerService,
        private readonly EntityRepository $sezzleCustomerRepository
    ) {
    }
    #[Route(path: '/api/_action/sezzle/customers/sync', name: 'api.action.sezzle.customers.sync', methods: ['POST'])]
    public function syncCustomers(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->request->get('salesChannelId');
        if (!$salesChannelId) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Sales channel ID is required',
            ], 400);
        }
        $result = $this->sezzleCustomerService->syncCustomersFromSezzle($salesChannelId, $context);
        return new JsonResponse($result);
    }
    #[Route(path: '/api/sezzle/customers', name: 'api.sezzle.customers.list', methods: ['GET'])]
    public function listCustomers(Request $request, Context $context): JsonResponse
    {
        $limit = (int) ($request->query->get('limit') ?? 25);
        $offset = (int) ($request->query->get('offset') ?? 0);
        $customers = $this->sezzleCustomerService->getAllCustomers($context, $limit, $offset);
        $data = [];
        foreach ($customers as $customer) {
            $data[] = [
                'id' => $customer->getId(),
                'sezzleCustomerUuid' => $customer->getSezzleCustomerUuid(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'isTokenized' => $customer->isTokenized(),
                'tokenizedAt' => $customer->getTokenizedAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
                'shopwareCustomerId' => $customer->getShopwareCustomerId(),
                'salesChannelId' => $customer->getSalesChannelId(),
            ];
        }
        return new JsonResponse([
            'data' => $data,
            'total' => $this->sezzleCustomerService->getTotalCount($context),
        ]);
    }
    #[Route(path: '/api/sezzle/customers/{customerId}', name: 'api.sezzle.customers.detail', methods: ['GET'])]
    public function getCustomer(string $customerId, Context $context): JsonResponse
    {
        $customer = null;
        try {
            $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$customerId]);
            $criteria->addAssociation('shopwareCustomer');
            $criteria->addAssociation('salesChannel');
            $customer = $this->sezzleCustomerRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $customer = $this->sezzleCustomerService->getCustomerByUuid($customerId, $context);
        }
        if (!$customer) {
            return new JsonResponse([
                'error' => 'Customer not found',
            ], 404);
        }
        return new JsonResponse([
            'id' => $customer->getId(),
            'sezzleCustomerUuid' => $customer->getSezzleCustomerUuid(),
            'merchantUuid' => $customer->getMerchantUuid(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'dateOfBirth' => $customer->getDateOfBirth()?->format('Y-m-d'),
            'billingAddress' => $customer->getBillingAddress(),
            'shippingAddress' => $customer->getShippingAddress(),
            'isTokenized' => $customer->isTokenized(),
            'tokenizedAt' => $customer->getTokenizedAt()?->format('Y-m-d H:i:s'),
            'shopwareCustomerId' => $customer->getShopwareCustomerId(),
            'salesChannelId' => $customer->getSalesChannelId(),
            'rawData' => $customer->getRawData(),
            'createdAt' => $customer->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $customer->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }
    #[Route(path: '/api/_action/sezzle/customers/{customerId}/charge', name: 'api.action.sezzle.customers.charge', methods: ['POST'])]
    public function chargeCustomer(string $customerId, Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->request->get('salesChannelId');
        $orderData = $request->request->all();
        unset($orderData['salesChannelId']);
        if (!$salesChannelId) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Sales channel ID is required',
            ], 400);
        }
        $requiredFields = ['amount', 'currency'];
        foreach ($requiredFields as $field) {
            if (!isset($orderData[$field])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => "Field '{$field}' is required",
                ], 400);
            }
        }
        $customer = null;
        try {
            $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$customerId]);
            $customer = $this->sezzleCustomerRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $customer = $this->sezzleCustomerService->getCustomerByUuid($customerId, $context);
        }
        if (!$customer) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Customer not found',
            ], 404);
        }
        $result = $this->sezzleCustomerService->chargeCustomer(
            $customer->getSezzleCustomerUuid(),
            $orderData,
            $salesChannelId,
            $context
        );
        $statusCode = $result['success'] ? 200 : 400;
        return new JsonResponse($result, $statusCode);
    }
}
