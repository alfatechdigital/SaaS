<?php

namespace App\Contracts;

use App\Data\WhatsAppCredentials;
use App\Data\WhatsAppMessage;
use App\Exceptions\WhatsAppDeliveryFailed;
use App\Support\WhatsApp\LogDriver;
use App\Support\WhatsApp\MetaCloudDriver;

/**
 * One way of getting a message to WhatsApp.
 *
 * Implementations are chosen by `config('whatsapp.driver')`, so the queue job,
 * the sender and every future caller stay identical whether the message goes to
 * Meta's Cloud API or to the log.
 *
 * @see MetaCloudDriver
 * @see LogDriver
 */
interface WhatsAppDriver
{
    /**
     * Deliver the message and return the provider's message id when it has one.
     *
     * @throws WhatsAppDeliveryFailed when the provider refuses the message or is unreachable
     */
    public function send(WhatsAppCredentials $credentials, WhatsAppMessage $message): ?string;
}
