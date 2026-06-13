<?php

declare(strict_types=1);

namespace PHPdot\Mail\Message;

use PHPdot\Mail\Exception\MailException;

/**
 * An email address with an optional display name. Validated on construction so a
 * malformed address fails where it is set, not deep inside the transport.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final readonly class Mailbox
{
    public function __construct(
        public string $email,
        public string $name = '',
    ) {
        if (!str_contains($email, '@') || str_contains($email, ' ')) {
            throw new MailException(sprintf('Invalid email address: "%s".', $email));
        }
    }
}
