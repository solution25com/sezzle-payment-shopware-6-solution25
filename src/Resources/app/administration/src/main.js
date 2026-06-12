import './module';
import './snippet/en-GB.json';
import './snippet/de-DE.json';
import SezzleCustomerService from './service/sezzle-customer.service';
import SezzleWebhookService from './service/sezzle-webhook.service';
import SezzleOrderCreationService from './service/sezzle-order-creation.service';

const { Locale } = Shopware;

Locale.extend('en-GB', () => import('./snippet/en-GB.json'));
Locale.extend('de-DE', () => import('./snippet/de-DE.json'));

Shopware.Service().register('sezzleCustomerService', () => {
    return new SezzleCustomerService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Shopware.Service().register('sezzleWebhookService', () => {
    return new SezzleWebhookService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});

Shopware.Service().register('sezzleOrderCreationService', () => {
    return new SezzleOrderCreationService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});
