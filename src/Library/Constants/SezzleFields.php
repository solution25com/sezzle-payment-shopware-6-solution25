<?php

namespace Sezzle\Library\Constants;

class SezzleFields
{
    public const ORDER_CF_ORDER_UUID = 'sezzleOrderUuid';
    public const ORDER_CF_CHECKOUT_UUID = 'sezzleCheckoutUuid';
    public const ORDER_CF_SESSION_UUID = 'sezzleSessionUuid';
    public const ORDER_CF_STATUS = 'sezzleStatus';
    public const ORDER_CF_AMOUNT = 'sezzleAmount';
    public const ORDER_CF_CURRENCY = 'sezzleCurrency';
    public const ORDER_CF_CAPTURE_UUID = 'sezzleCaptureUuid';
    public const ORDER_CF_REFUND_UUID = 'sezzleRefundUuid';
    public const CUSTOMER_CF_ORDER_UUID = 'sezzleOrderUuid';
    public const CUSTOMER_CF_CHECKOUT_UUID = 'sezzleCheckoutUuid';
    public const SEZZLE_ORDER_UUID = 'sezzleOrderUuid';
    public const SEZZLE_CHARGE_RESPONSE = 'sezzleChargeResponse';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_AUTHORIZED = 'Authorized';
    public const STATUS_CAPTURED = 'Captured';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_REFUNDED = 'Refunded';
}
