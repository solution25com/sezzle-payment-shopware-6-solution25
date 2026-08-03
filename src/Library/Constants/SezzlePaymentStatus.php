<?php

declare(strict_types=1);

namespace Sezzle\Library\Constants;

final class SezzlePaymentStatus
{
    public const VERIFIED_CAPTURED = 'verified_captured';
    public const VERIFIED_AUTHORIZED = 'verified_authorized';
    public const DECLINED = 'declined';
    public const PENDING = 'pending';
}
