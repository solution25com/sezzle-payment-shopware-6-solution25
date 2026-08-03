<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->import('services/gateways/sezzlePaymentHandler.xml');
    $container->import('services/subscriber/checkoutConfirmEventSubscriber.xml');
    $container->import('services/services/configService.xml');
    $container->import('services/services/sezzleClientServices.xml');
    $container->import('services/services/sezzlePaymentVerification.xml');
    $container->import('services/services/orderTransactionMapper.xml');
    $container->import('services/services/paymentTransactionStateHandler.xml');
    $container->import('services/services/logger.xml');
    $container->import('services/services/sezzleCustomerService.xml');
    $container->import('services/services/dal.xml');
    $container->import('services/services/sezzleTokenizationService.xml');
    $container->import('services/services/adminOrderCreationService.xml');
    $container->import('services/subscriber/orderSubscribers.xml');
    $container->import('services/controller/sezzleTestApiController.xml');
    $container->import('services/controller/sezzleCustomerController.xml');
    $container->import('services/controller/sezzleWebhookController.xml');
};
