<?php

declare(strict_types=1);

namespace PHPdot\Mail\Contract;

use PHPdot\Mail\Message\Message;

/**
 * The injectable entry point to the package: start a message and send it. Bound
 * to {@see \PHPdot\Mail\Mailer} as a container singleton, so a consumer can
 * depend on this contract rather than the concrete service.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface MailerInterface
{
    /**
     * Start composing a message. Chain builders on it and finish with `->send()`
     * — or start the chain directly from one of the shortcuts below, e.g.
     * `$mail->to('a@b.com')->subject('Hi')->html('<p>Hi</p>')->send()`.
     */
    public function message(): Message;

    public function from(string $email, string $name = ''): Message;

    public function to(string $email, string $name = ''): Message;

    public function cc(string $email, string $name = ''): Message;

    public function bcc(string $email, string $name = ''): Message;

    public function replyTo(string $email, string $name = ''): Message;

    public function subject(string $subject): Message;

    public function html(string $html): Message;

    public function text(string $text): Message;
}
