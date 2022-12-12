# Mail Transport Adapter

Email sending from Schoolbox is done via its adapter, which abstracts around the
configuration details of different SMTP services and provides convenient methods
for constructing and sending emails.

The adapter also handles the attachment of
[DKIM](https://en.wikipedia.org/wiki/DomainKeys_Identified_Mail) headers to
outgoing emails.

The adapter wraps around an instance of the
[Symfony mailer](https://symfony.com/doc/current/mailer.html).

The adapter contains the following classes:
- `\Schoolbox\Mail\EmailFactory`: builds email objects for sending
- `\Alaress\Mail\Mailer`: base class for email objects
- `\Schoolbox\Mail\TwigEmail`: extension of `\Alaress\Mail\Mailer` for sending
  emails based on a Twig template


## Instantiation

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \Schoolbox\Mail\EmailFactory $emailFactory */
$emailFactory = $di->get('mail.emailFactory');
```

## Usage

```php
/** @var \Schoolbox\Mail\EmailFactory $emailFactory */

// Build a simple email with user-generated content
$simpleEmail = $emailFactory->buildSimpleEmail($subject, $to);
$simpleEmail->setHtml($htmlContent);
$simpleEmail->send();

// Build an email from a Twig template
$twigEmail = $emailFactory->buildTwigEmail($template, $subject, $to);
$twigEmail->setContext($twigVariables);
$twigEmail->send();
```
