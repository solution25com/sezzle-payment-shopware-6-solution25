<?php

declare(strict_types=1);

namespace Sezzle\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SezzleValidationException extends ShopwareHttpException
{
    public function __construct(string $message = 'Sezzle validation error', array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }
    public function getErrorCode(): string
    {
        return 'SEZZLE__VALIDATION_ERROR';
    }
    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
