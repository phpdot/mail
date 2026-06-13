<?php

declare(strict_types=1);

namespace PHPdot\Mail\Exception;

/**
 * Base for every exception thrown by the package — catch this to trap any mail
 * failure.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
class MailException extends \RuntimeException {}
