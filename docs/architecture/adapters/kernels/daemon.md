# Daemon Kernel

The daemon kernel `\Schoolbox\Application\Daemon\Kernel` is a variant of the
[CLI kernel](cli.md), designed for the specific requirements of daemons.

In addition to the CLI kernel's responsibilities, it is able to:
- create a loop for running code asynchronously
- handle errors in a manner which allows for recovery, or a graceful exit so
  that the host operating system's service manager
  (e.g. [systemd](https://systemd.io/)) may restart the process

## Running a request

The daemon kernel will run its `run` method once it is called: any timers or
streams set up on `$loop` will run, and only once they finish or are interrupted
will the daemon terminate.

See https://github.com/reactphp/event-loop for more information on the event
loop.

```php
$kernel = new \Schoolbox\Application\Daemon\Kernel($name, $options);
$kernel->run(
    function (
        \Psr\Container\ContainerInterface $di,
        \React\EventLoop\LoopInterface $loop,
        \Psr\Log\LoggerInterface $log,
        array $opts,
    ) {
        // Timers or streams will be set up on `$loop` either:
        // * in here directly
        // * in objects this callback creates
        // * as part of creating a Redis queue subscription
        // After this callback returns: the loop will run, and only terminate
        // when:
        // * all timers/streams on the loop terminate on their own
        // * a timer/stream callback raises an error
        // * the process receives an interrupt
    }
);
```
