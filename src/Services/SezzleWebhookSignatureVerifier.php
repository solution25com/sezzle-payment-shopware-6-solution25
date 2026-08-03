<?php

declare(strict_types=1);

namespace Sezzle\Services;

use Symfony\Component\HttpFoundation\Request;

final class SezzleWebhookSignatureVerifier
{
    public function verify(Request $request, string $merchantPrivateKey): bool
    {
        $signature = $request->headers->get('Sezzle-Signature');
        if (!is_string($signature) || $signature === '' || $merchantPrivateKey === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $merchantPrivateKey);

        return hash_equals($expected, $signature);
    }
}
