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
```
