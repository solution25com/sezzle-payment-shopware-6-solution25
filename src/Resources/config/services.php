<?php
declare(strict_types=1);
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
return static function (ContainerBuilder $container): void {
    $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/services'));
    $loader->load('gateways/sezzlePaymentHandler.xml');
    $loader->load('subscriber/checkoutConfirmEventSubscriber.xml');
    $loader->load('services/configService.xml');
    $loader->load('services/sezzleClientServices.xml');
    $loader->load('services/orderTransactionMapper.xml');
    $loader->load('services/paymentTransactionStateHandler.xml');
    $loader->load('services/logger.xml');
    $loader->load('services/sezzleCustomerService.xml');
    $loader->load('services/dal.xml');
    $loader->load('services/sezzleTokenizationService.xml');
    $loader->load('services/adminOrderCreationService.xml');
    $loader->load('subscriber/orderSubscribers.xml');
    $loader->load('controller/sezzleTestApiController.xml');
    $loader->load('controller/sezzleCustomerController.xml');
    $loader->load('controller/sezzleWebhookController.xml');
};
