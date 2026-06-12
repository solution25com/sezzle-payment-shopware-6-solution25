<?php
declare(strict_types=1);
namespace Sezzle\DataAbstractionLayer\Entity\SezzleCustomer;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
class SezzleCustomerCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SezzleCustomerEntity::class;
    }
}
