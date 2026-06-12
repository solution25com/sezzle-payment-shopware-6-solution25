<?php
declare(strict_types=1);
namespace Sezzle\Migration;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
class Migration1735689600CreateSezzleCustomerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1735689600;
    }
    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `sezzle_customer` (
    `id` BINARY(16) NOT NULL,
    `sezzle_customer_uuid` VARCHAR(255) NOT NULL,
    `merchant_uuid` VARCHAR(255) DEFAULT NULL,
    `first_name` VARCHAR(255) DEFAULT NULL,
    `last_name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `billing_address` JSON DEFAULT NULL,
    `shipping_address` JSON DEFAULT NULL,
    `is_tokenized` TINYINT(1) DEFAULT 0,
    `tokenized_at` DATETIME(3) DEFAULT NULL,
    `sales_channel_id` BINARY(16) DEFAULT NULL,
    `shopware_customer_id` BINARY(16) DEFAULT NULL,
    `raw_data` JSON DEFAULT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.sezzle_customer.uuid` (`sezzle_customer_uuid`),
    KEY `idx.sezzle_customer.email` (`email`),
    KEY `idx.sezzle_customer.shopware_customer_id` (`shopware_customer_id`),
    KEY `idx.sezzle_customer.sales_channel_id` (`sales_channel_id`),
    CONSTRAINT `fk.sezzle_customer.shopware_customer_id` FOREIGN KEY (`shopware_customer_id`) 
        REFERENCES `customer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.sezzle_customer.sales_channel_id` FOREIGN KEY (`sales_channel_id`) 
        REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }
    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `sezzle_customer`');
    }
}
