<?php

namespace App\Data;

/**
 * What a gateway returns when it has created a payable transaction.
 *
 * `orderId` is our invoice number, so the provider always knows which period it is
 * charging. `token` and `redirectUrl` are what the tenant needs to actually pay;
 * they are stored on the invoice so the UI (26 Nov) can show the same link again
 * instead of creating a second transaction.
 */
final class PaymentTransaction
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $token,
        public readonly string $redirectUrl,
    ) {}
}
