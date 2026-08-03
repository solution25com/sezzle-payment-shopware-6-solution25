<?php

namespace Sezzle\Gateways;

class SezzlePaymentMethod
{
    public function getName(): string
    {
        return 'Sezzle Payment';
    }
    public function getDescription(): string
    {
        return 'Buy Now Pay Later with the Sezzle App';
    }
    public function getHandlerIdentifier(): string
    {
        return SezzlePaymentHandler::class;
    }
    public function getTechnicalName(): string
    {
        return 'sezzle_payment';
    }
}
