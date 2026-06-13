<?php

declare(strict_types=1);

namespace PHPdot\Mail\Message;

use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\Receipt;

/**
 * An email under construction. Immutable — every setter returns a new message,
 * so a configured base (shared sender, headers) is a safe reusable template, and
 * a chain off a shared Mailer never mutates it. The reader methods are how the
 * transport boundary inspects it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Message
{
    private ?Mailbox $from = null;

    /** @var list<Mailbox> */
    private array $to = [];

    /** @var list<Mailbox> */
    private array $cc = [];

    /** @var list<Mailbox> */
    private array $bcc = [];

    /** @var list<Mailbox> */
    private array $replyTo = [];

    private string $subject = '';
    private ?string $html = null;
    private ?string $text = null;

    /** @var list<Attachment> */
    private array $attachments = [];

    private ?int $priority = null;

    /** @var array<string, string> */
    private array $headers = [];

    /**
     * @param (\Closure(self): Receipt)|null $dispatch how to deliver this message,
     *                                                 wired in by the Mailer that started the chain; null for a stand-alone
     *                                                 `new Message()`, which is sent via Mailer::send() instead
     */
    public function __construct(
        private readonly ?\Closure $dispatch = null,
    ) {}

    public function from(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->from = new Mailbox($email, $name);

        return $clone;
    }

    public function to(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->to[] = new Mailbox($email, $name);

        return $clone;
    }

    public function cc(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->cc[] = new Mailbox($email, $name);

        return $clone;
    }

    public function bcc(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->bcc[] = new Mailbox($email, $name);

        return $clone;
    }

    public function replyTo(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->replyTo[] = new Mailbox($email, $name);

        return $clone;
    }

    public function subject(string $subject): self
    {
        $clone = clone $this;
        $clone->subject = $subject;

        return $clone;
    }

    public function html(string $html): self
    {
        $clone = clone $this;
        $clone->html = $html;

        return $clone;
    }

    public function text(string $text): self
    {
        $clone = clone $this;
        $clone->text = $text;

        return $clone;
    }

    /**
     * Attach a file from disk; the name defaults to its basename.
     */
    public function attach(string $path, ?string $name = null): self
    {
        $clone = clone $this;
        $clone->attachments[] = Attachment::fromPath($path, $name);

        return $clone;
    }

    /**
     * Attach raw bytes already in memory under the given file name.
     */
    public function attachData(string $body, string $name, ?string $contentType = null): self
    {
        $clone = clone $this;
        $clone->attachments[] = Attachment::fromData($body, $name, $contentType);

        return $clone;
    }

    /**
     * Importance from 1 (highest) to 5 (lowest).
     */
    public function priority(int $priority): self
    {
        $clone = clone $this;
        $clone->priority = $priority;

        return $clone;
    }

    public function header(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /**
     * Send this message — the tail of a `$mail->to(...)->subject(...)->send()`
     * chain. Available only on a message the Mailer started; a stand-alone
     * `new Message()` has no mailer, so deliver it via {@see \PHPdot\Mail\Mailer::send()}.
     *
     * @throws MailException when the message was not started from a Mailer
     */
    public function send(): Receipt
    {
        return ($this->dispatch ?? throw new MailException(
            'This message was not started from a Mailer; send it with Mailer::send().',
        ))($this);
    }

    // ─── readers consumed by the transport boundary ───

    public function sender(): ?Mailbox
    {
        return $this->from;
    }

    /** @return list<Mailbox> */
    public function recipients(): array
    {
        return $this->to;
    }

    /** @return list<Mailbox> */
    public function carbonCopies(): array
    {
        return $this->cc;
    }

    /** @return list<Mailbox> */
    public function blindCarbonCopies(): array
    {
        return $this->bcc;
    }

    /** @return list<Mailbox> */
    public function replyAddresses(): array
    {
        return $this->replyTo;
    }

    public function subjectLine(): string
    {
        return $this->subject;
    }

    public function htmlBody(): ?string
    {
        return $this->html;
    }

    public function textBody(): ?string
    {
        return $this->text;
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return $this->attachments;
    }

    public function priorityLevel(): ?int
    {
        return $this->priority;
    }

    /** @return array<string, string> */
    public function customHeaders(): array
    {
        return $this->headers;
    }
}
