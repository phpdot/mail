<?php

declare(strict_types=1);

namespace PHPdot\Mail\Exception;

/**
 * Thrown when the underlying transport fails to deliver a message (connection
 * refused, authentication rejected, recipient declined, etc.).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class TransportException extends MailException {}
