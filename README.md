# phpdot/mail

Coroutine-safe transactional email for the PHPdot ecosystem. Compose a message with a fluent, immutable builder and send it through any transport — **SMTP**, **sendmail**, or any Symfony transport — from one injectable service:

```php
$mail->to('alice@example.com')->subject('Welcome')->html($body)->send();
```

Delivery is delegated to the battle-tested [`symfony/mailer`][symfony-mailer] + `symfony/mime`, fenced entirely behind a single transport boundary — the rest of the package (the builder, the value objects, the receipt) is plain PHPdot code. A fresh transport is built per send, so concurrent coroutines never share a socket under Swoole.

## Install

```bash
composer require phpdot/mail
```

| Requirement | Version |
|---|---|
| PHP | >= 8.3 |
| symfony/mailer | ^7.0 |
| symfony/mime | ^7.0 |
| phpdot/package | optional — auto-wiring + `config/mail.php` scaffolding (with phpdot/container, phpdot/config) |

## Quick Start

```php
use PHPdot\Mail\Mailer;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Transport\EmailFactory;
use PHPdot\Mail\Transport\Transport;

// In an app you inject Mailer — see "DI Wiring". Wired by hand here:
$mail = new Mailer(
    new MailConfig(dsn: 'smtp://user:pass@smtp.example.com:587', fromEmail: 'no-reply@example.com'),
    new Transport(new EmailFactory()),
);

$receipt = $mail
    ->to('alice@example.com', 'Alice')
    ->subject('Welcome aboard')
    ->html('<h1>Hi Alice</h1>')
    ->text('Hi Alice')
    ->send();

echo $receipt->messageId; // '<a1b2c3@mail.example.com>'
```

> **Every message needs a sender.** Set `fromEmail` in config (as above) so chains can omit `->from()` — otherwise call `->from('you@example.com', 'You')` on the message.

## Why phpdot/mail

- **Sends, nothing more.** The mailer's job is delivery. An HTML body is just a string — render it with `phpdot/template`, a heredoc, or anything else, and pass it to `->html()`. Zero coupling to a template engine.
- **Fluent and immutable.** `$mail->to(...)->subject(...)->send()` reads top to bottom. Each step returns a *new* `Message`, so chaining off the shared mailer never mutates it, and a configured base message is a safe reusable template.
- **Coroutine-safe.** The mailer is a stateless singleton and a fresh transport (its own socket) is built per send, so concurrent sends under Swoole never share a connection.
- **One dependency, well-fenced.** Symfony is reached only through the `Transport/` boundary. Every Symfony failure is translated into the package's own `MailException` / `TransportException`, so no Symfony type leaks into your code.
- **Honest about delivery.** `send()` returns a `Receipt` (message id) when the transport accepts the message and throws when it's rejected — and the docs are explicit that *accepted is not delivered* (see [Outcomes](#outcomes)).
- **Strict.** `declare(strict_types=1)` throughout, PHPStan level 10 with strict rules, zero ignored errors.

## Architecture

```
src/
├── Mailer.php              #[Singleton] #[Binds] — inject this; compose a message
├── MailConfig.php          #[Config('mail')] — transport DSN + default sender
├── Receipt.php             the outcome of an accepted send (message id + debug)
├── Contract/
│   └── MailerInterface.php
├── Message/
│   ├── Message.php         immutable fluent builder; send() delivers it
│   ├── Mailbox.php         a validated address (email + name)
│   └── Attachment.php      a file on disk or raw bytes in memory
├── Transport/
│   ├── Transport.php       builds a per-send transport from the DSN, delivers
│   └── EmailFactory.php    maps a Message onto a symfony/mime Email
└── Exception/
    ├── MailException.php       base — catch this for anything from the package
    └── TransportException.php  the transport could not deliver
```

Flow: inject `Mailer` → `$mail->to(...)` starts a fresh `Message` → `->send()` hands it to `Transport`, which maps it to a MIME email (`EmailFactory`), builds a one-shot transport from the DSN, delivers, and returns a `Receipt`. Symfony lives only inside `Transport/`.

## Composing a Message

A message is built fluently and immutably — start it from the mailer (`$mail->to(...)`) or with `$mail->message()`, then chain:

```php
$message = $mail->message()
    ->from('no-reply@example.com', 'Acme')
    ->to('alice@example.com', 'Alice')
    ->cc('manager@example.com')
    ->bcc('audit@example.com')
    ->replyTo('support@example.com')
    ->subject('Your invoice')
    ->text('Plain-text fallback')
    ->html('<p>Rich HTML body</p>')
    ->attach('/path/invoice.pdf', 'invoice.pdf')
    ->priority(1)
    ->header('X-Campaign', 'invoices');

$receipt = $message->send();
```

| `Message` builder | |
|---|---|
| `from` · `to` · `cc` · `bcc` · `replyTo` `(email, name = '')` | Addresses — validated on the spot. |
| `subject(string)` | Subject line. |
| `html(string)` · `text(string)` | HTML body · plain-text part (set both for multipart). |
| `attach(path, name = null)` | Attach a file from disk. |
| `attachData(bytes, name, contentType = null)` | Attach raw bytes already in memory. |
| `priority(int)` | 1 (highest) … 5 (lowest). |
| `header(name, value)` | A custom header. |
| `send(): Receipt` | Deliver the message. |

Every setter returns a new `Message`, so `$base = $mail->from('no-reply@acme.com', 'Acme')` is a reusable template you branch per recipient.

## Sending

When you don't need to hold the message, chain straight through to `send()`:

```php
$receipt = $mail->to('alice@example.com')->subject('Hi')->html($body)->send();
```

The shortcuts (`from`/`to`/`cc`/`bcc`/`replyTo`/`subject`/`html`/`text`) live on `MailerInterface`, so the chain works through the injected contract, not just the concrete class.

## Outcomes

`send()` tells you two things, and deliberately cannot tell you a third:

| Outcome | How you get it |
|---|---|
| **Accepted** — the transport took the message | a `Receipt` (`messageId` + the SMTP `debug` transcript) |
| **Rejected** — connection / auth / recipient / format failure | a thrown `TransportException` / `MailException` |
| **Delivered, bounced, or spam-filtered** | *not knowable here* — only your provider's webhooks report it |

```php
use PHPdot\Mail\Exception\TransportException;

try {
    $receipt = $message->send();
    $logger->info('mail accepted', ['id' => $receipt->messageId]);
} catch (TransportException $e) {
    $logger->error('mail rejected', ['why' => $e->getMessage()]);
}
```

**"Accepted" means handed off to the mail server, not delivered to the inbox.** Bounces and spam filtering happen asynchronously — keep the `Receipt`'s `messageId` to correlate this send with the delivery and bounce webhooks your provider sends later.

## Transports

The transport is chosen by the DSN in config — anything `symfony/mailer` supports:

| DSN | Transport |
|---|---|
| `smtp://user:pass@host:587` | SMTP |
| `sendmail://default` | local sendmail |
| `native://default` | the MTA configured in `php.ini` |
| `null://null` | discard — the default, sends nowhere |

Provider APIs (SES, Postmark, Mailgun, …) work too: install the matching `symfony/*-mailer` bridge and use its DSN.

## Configuration

`MailConfig` is the typed config, scaffolded into `config/mail.php` by `phpdot/package`:

| Key | Default | |
|---|---|---|
| `dsn` | `null://null` | Transport DSN — read it from `MAIL_DSN`. |
| `fromEmail` | `''` | Default sender, used when a message sets no `from`. |
| `fromName` | `''` | Default sender display name. |

```php
// config/mail.php  (generated once; edit freely)
return [
    'dsn'       => env('MAIL_DSN', 'smtp://localhost:1025'),
    'fromEmail' => env('MAIL_FROM', 'no-reply@example.com'),
    'fromName'  => 'Acme',
];
```

## DI Wiring

`Mailer` is `#[Singleton]` and `#[Binds(MailerInterface::class)]`, `MailConfig` is `#[Config('mail')]`, and the transport pieces are `#[Singleton]` — so with `phpdot/package` everything autowires and `config/mail.php` is scaffolded. Nothing to register.

```php
use PHPdot\Mail\Contract\MailerInterface;

final class WelcomeController
{
    public function __construct(private MailerInterface $mail) {}

    public function register(string $email, string $body): void
    {
        $this->mail->to($email)->subject('Welcome')->html($body)->send();
    }
}
```

It is stateless (each `send()` builds its own transport), so the single shared instance is **coroutine-safe** under Swoole.

## Development

```bash
composer test      # PHPUnit (Unit + Integration)
composer analyse   # PHPStan level 10, strict rules
composer cs-check  # php-cs-fixer dry run
composer check     # all three
```

## License

MIT — see [LICENSE](LICENSE).

[symfony-mailer]: https://github.com/symfony/mailer
