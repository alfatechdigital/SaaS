<?php

namespace App\Data;

use App\Models\Team;

/**
 * The credentials of one tenant's WhatsApp Business number.
 *
 * Built from the `teams` columns by {@see Team::whatsappCredentials()},
 * so a driver never touches the model and never sees a decrypted token it did not
 * ask for.
 */
final readonly class WhatsAppCredentials
{
    public function __construct(
        public string $phoneNumberId,
        public string $accessToken,
        public ?string $businessAccountId = null,
    ) {
        //
    }
}
