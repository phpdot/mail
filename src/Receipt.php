<?php

declare(strict_types=1);

namespace PHPdot\Mail;

/**
 * The outcome of an accepted send: the transport took the message for delivery.
 * Carries the message ID — keep it to correlate this send with the delivery and
 * bounce webhooks your provider sends later — and the transport's debug
 * transcript.
 *
 * "Accepted" means handed off to the mail server, not delivered to the inbox:
 * bounces and spam filtering happen asynchronously and are reported by the
 * provider's webhooks, never by the send call itself.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final readonly class Receipt
{
    public function __construct(
        public string $messageId,
        public string $debug = '',
    ) {}
}
