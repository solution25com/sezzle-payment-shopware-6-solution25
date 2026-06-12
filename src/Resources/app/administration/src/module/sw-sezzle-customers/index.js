import './page/sw-sezzle-customers-list';
import './page/sw-sezzle-customers-detail';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-sezzle-customers', {
    type: 'plugin',
    name: 'sezzle.customers',
    title: 'sezzle.customers.title',
    description: 'sezzle.customers.description',
    version: '1.0.0',
    targetVersion: '6.7.0.0',
    color: '#ff6b6b',
    entity: 'sezzle_customer',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        swSezzleCustomers: {
            component: 'sw-sezzle-customers-list',
            path: 'sw-sezzle-customers',
        },

        index: {
            component: 'sw-sezzle-customers-list',
            path: 'index',
            meta: {
                parentPath: 'sw.customer.index',
            },
        },

        detail: {
            component: 'sw-sezzle-customers-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.sezzle.customers.index',
            },
        },
    },

    navigation: [{
        id: 'sw-sezzle-customers',
        label: 'sezzle.customers.navigation',
        parent: 'sw-customer',
        path: 'sw.sezzle.customers.index',
        position: 100,
    }],
});
