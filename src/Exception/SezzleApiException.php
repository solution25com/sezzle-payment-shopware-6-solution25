<?php
declare(strict_types=1);
namespace Sezzle\Exception;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
class SezzleApiException extends ShopwareHttpException
{
    public function __construct(string $message = 'Sezzle API error', array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }
    public function getErrorCode(): string
    {
        return 'SEZZLE__API_ERROR';
    }
    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_GATEWAY;
    }
}
