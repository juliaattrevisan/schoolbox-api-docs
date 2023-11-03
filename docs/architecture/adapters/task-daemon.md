# Task daemon adapter

Communication with the task daemon is performed via its adapter, which abstracts
away the underlying [Redis](redis.md) instance and provides convenient methods
for building requests.

Internally, the task daemon queue uses [Enqueue](https://enqueue.forma-pro.com/)
as its library for sending and encoding messages.

The adapter's code is stored in the `\Schoolbox\TaskDaemon` namespace, and
includes:
- `\Schoolbox\TaskDaemon\Producer`: wraps around the Enqueue client, removing
  message sending boilerplate and enforces that messages meet a specified
  interface
- `\Schoolbox\TaskDaemon\Request\MessageFactory` for building
  `\Schoolbox\TaskDaemon\Request\Message` instances
  - child classes may provide more refined interfaces for building messages,
    e.g. `\Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory` for
    building a message for requesting to download a set of pages as a PDF
- `\Schoolbox\TaskDaemon\Request\Message`: for enforcing a minimum set of data
  which a message sent to the task daemon must provide
- `\Schoolbox\TaskDaemon\Request\MessageSerializer`: for converting a message
  to a form which may be transmitted via Enqueue

## Instantiation

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \Schoolbox\TaskDaemon\Producer $messageProducer */
$messageProducer = $di->get('taskd.messageProducer');

/** @var \Schoolbox\TaskDaemon\Request\MessageFactory $messageFactory */
$messageFactory = $di->get('taskd.messageFactory');

// Specific factory for DownloadPages messages
// (download files or PDFs from a set of URLs, convert to a ZIP)
/** @var \Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory $downloadPagesMessageFactory */
$downloadPagesMessageFactory = $di->get('taskd.downloadPagesMessageFactory');

/** @var \Http\Discovery\Psr17Factory $httpFactory */
$httpFactory = $kernel->get('httpFactory');
```

## Usage

```php
/** @var \Schoolbox\TaskDaemon\Producer $messageProducer */
/** @var \Schoolbox\TaskDaemon\Request\MessageFactory $messageFactory */
$message = $messageFactory->build(
    $taskName,
    $taskParams,
    $callbackUrl,
    $callbackParams,
    $user,
);
$messageProducer->send($message);

// More specific message factories exist
/** @var \Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory $downloadPagesMessageFactory */
$message = $downloadPagesMessageFactory
    ->addFileContent('image.jpg', 'https://example.com/image.jpg')
    ->addPdfContent('index.pdf', 'https://instance.schoolbox.cloud/')
    ->setOutfile('output.zip')
    ->build();
$messageProducer->send($message);

// For sending a HTTP request:
$messageFactory = new \Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory(
    $kernel->get('instance'),
    $kernel->get('user.tokenBuilder'),
);
/** @var \Http\Discovery\Psr17Factory $httpFactory */
$uri     = new Schoolbox\Url\Uri('https://example.com');
$message = $httpRequestMessageFactory
    ->setRequest($httpFactory->createRequest('GET', $uri))
    ->build();
$messageProducer->send($message);
```

### Delaying message sending

A message may be delayed a given number of seconds:

```php
/** @var \Schoolbox\TaskDaemon\Producer $messageProducer */
$messageProducer->send($message, delay: 1.25);
```

### Customising maximum number of requeues

A message will by default be requeued up to three times, if an unexpected error
occurs or the job reports that it is not yet complete.

If a message is requeued: it will not be retried immediately, instead it will be
subject to exponential backoff.

```php
/** @var \Schoolbox\TaskDaemon\Producer $messageProducer */
$messageProducer->send($message, maxRequeues: 10);
```

### Test job failure

A failure of the message's job may also be simulated: useful for testing the
resilience of the code which is sending the message.

```php
/** @var \Schoolbox\TaskDaemon\Producer $messageProducer */
$messageProducer->send(
    $message,
    testErrorChance: 0.5,
    testErrorStatus: Schoolbox\TaskDaemon\Task\Status::FAILURE,
    // Determining a payload requires knowledge of the task being run:
    // for error testing, you should be able to leave this blank
    testErrorPayload: [],
);
```
