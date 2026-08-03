<?php

namespace Sezzle\Services;

abstract class Endpoints
{
    protected const AUTH_TOKEN = 'AUTH_TOKEN';
    protected const CREATE_SESSION = 'CREATE_SESSION';
    protected const GET_SESSION = 'GET_SESSION';
    protected const CREATE_ORDER = 'CREATE_ORDER';
    protected const GET_ORDER = 'GET_ORDER';
    protected const CAPTURE_ORDER = 'CAPTURE_ORDER';
    protected const REFUND_ORDER = 'REFUND_ORDER';
    protected const RELEASE_ORDER = 'RELEASE_ORDER';
    protected const DELETE_CHECKOUT = 'DELETE_CHECKOUT';
    protected const CREATE_CUSTOMER = 'CREATE_CUSTOMER';
    protected const GET_CUSTOMER = 'GET_CUSTOMER';
    protected const LIST_CUSTOMERS = 'LIST_CUSTOMERS';
    protected const DELETE_CUSTOMER = 'DELETE_CUSTOMER';
    protected const CHARGE_CUSTOMER = 'CHARGE_CUSTOMER';
    protected const WEBHOOK = 'sezzle/webhook';
    protected const WEBHOOK_CREATE = 'WEBHOOK_CREATE';
    protected const WEBHOOK_LIST = 'WEBHOOK_LIST';
    protected const WEBHOOK_TEST = 'WEBHOOK_TEST';
    protected const WEBHOOK_DELETE = 'WEBHOOK_DELETE';
    protected const UPDATE_CHECKOUT = 'UPDATE_CHECKOUT';
    private static array $endpoints = [
        self::AUTH_TOKEN => [
            'method' => 'POST',
            'url' => '/v2/authentication'
        ],
        self::CREATE_SESSION => [
            'method' => 'POST',
            'url' => '/v2/session'
        ],
        self::GET_SESSION => [
            'method' => 'GET',
            'url' => '/v2/session/{sessionToken}'
        ],
        self::CREATE_ORDER => [
            'method' => 'POST',
            'url' => '/v2/order'
        ],
        self::GET_ORDER => [
            'method' => 'GET',
            'url' => '/v2/order/{orderUuid}'
        ],
        self::CAPTURE_ORDER => [
            'method' => 'POST',
            'url' => '/v2/order/{orderUuid}/capture'
        ],
        self::REFUND_ORDER => [
            'method' => 'POST',
            'url' => '/v2/order/{orderUuid}/refund'
        ],
        self::RELEASE_ORDER => [
            'method' => 'POST',
            'url' => '/v2/order/{orderUuid}/release'
        ],
        self::DELETE_CHECKOUT => [
            'method' => 'DELETE',
            'url' => '/v2/order/{orderUuid}/checkout'
        ],
        self::CREATE_CUSTOMER => [
            'method' => 'POST',
            'url' => '/v2/customer'
        ],
        self::GET_CUSTOMER => [
            'method' => 'GET',
            'url' => '/v2/customer/{customerUuid}'
        ],
        self::LIST_CUSTOMERS => [
            'method' => 'GET',
            'url' => '/v2/customer'
        ],
        self::DELETE_CUSTOMER => [
            'method' => 'DELETE',
            'url' => '/v2/customer/{customerUuid}'
        ],
        self::CHARGE_CUSTOMER => [
            'method' => 'POST',
            'url' => '/v2/customer/{customerUuid}/order'
        ],
        self::WEBHOOK_CREATE => [
            'method' => 'POST',
            'url' => '/v2/webhooks'
        ],
        self::WEBHOOK_LIST => [
            'method' => 'GET',
            'url' => '/v2/webhooks'
        ],
        self::WEBHOOK_TEST => [
            'method' => 'POST',
            'url' => '/v2/webhooks/test'
        ],
        self::WEBHOOK_DELETE => [
            'method' => 'DELETE',
            'url' => '/v2/webhooks/{webhookUuid}'
        ],
        self::UPDATE_CHECKOUT => [
            'method' => 'PATCH',
            'url' => '/v2/order/{orderUuid}/checkout'
        ],
    ];
    protected static function getEndpoint(string $endpoint): array
    {
        return self::$endpoints[$endpoint];
    }
    public static function callbackUrl(string $domain): string
    {
        return rtrim($domain, '/') . '/' . self::WEBHOOK;
    }
    public static function buildOrderUrl(string $orderUuid, array $endpoint): array
    {
        return [
            'method' => $endpoint['method'],
            'url' => str_replace('{orderUuid}', $orderUuid, $endpoint['url']),
        ];
    }
    public static function buildCustomerUrl(string $customerUuid, array $endpoint): array
    {
        return [
            'method' => $endpoint['method'],
            'url' => str_replace('{customerUuid}', $customerUuid, $endpoint['url']),
        ];
    }
    public static function buildWebhookUrl(string $webhookUuid, array $endpoint): array
    {
        return [
            'method' => $endpoint['method'],
            'url' => str_replace('{webhookUuid}', $webhookUuid, $endpoint['url']),
        ];
    }
}
