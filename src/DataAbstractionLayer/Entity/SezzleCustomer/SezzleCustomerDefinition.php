<?php
declare(strict_types=1);
namespace Sezzle\DataAbstractionLayer\Entity\SezzleCustomer;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
class SezzleCustomerDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'sezzle_customer';
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }
    public function getEntityClass(): string
    {
        return SezzleCustomerEntity::class;
    }
    public function getCollectionClass(): string
    {
        return SezzleCustomerCollection::class;
    }
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('sezzle_customer_uuid', 'sezzleCustomerUuid'))->addFlags(new Required()),
            new StringField('merchant_uuid', 'merchantUuid'),
            new StringField('first_name', 'firstName'),
            new StringField('last_name', 'lastName'),
            new StringField('email', 'email'),
            new StringField('phone', 'phone'),
            new DateField('date_of_birth', 'dateOfBirth'),
            new JsonField('billing_address', 'billingAddress'),
            new JsonField('shipping_address', 'shippingAddress'),
            new BoolField('is_tokenized', 'isTokenized'),
            new DateTimeField('tokenized_at', 'tokenizedAt'),
            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class),
            new FkField('shopware_customer_id', 'shopwareCustomerId', CustomerDefinition::class),
            new JsonField('raw_data', 'rawData'),
            new DateTimeField('created_at', 'createdAt'),
            new DateTimeField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id'),
            new ManyToOneAssociationField('shopwareCustomer', 'shopware_customer_id', CustomerDefinition::class, 'id'),
        ]);
    }
}
