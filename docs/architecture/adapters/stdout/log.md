# Log Adapter

Schoolbox uses the standard [Monolog](https://seldaek.github.io/monolog/) log
adapter for its logging.

## Instantiation

CLI and daemon kernels have a logger pointed to standard output already
configured.

> This logger is actually an instance of `\Alaress\Lumberjack\Logger`, which
> wraps around standard Monolog objects and performs formatting on log output.

```php
// within either the CLI or daemon kernel
/** @var \Psr\Log\LoggerInterface $logger */
$logger = $this->logger();
```

CLI apps also have the same logger already configured:

```php
// within either the CLI or daemon kernel
/** @var \Psr\Log\LoggerInterface $logger */
$logger = $this->getLogger();
```

Otherwise, a logger can be created manually following the Monolog documentation.

## Usage

```php
/** @var \Psr\Log\LoggerInterface $logger */
$logger->warning('Warning message');
```

See the Monolog documentation for further details.
