# Scheduler

The notification system uses the Scheduler to defer the processing of Messages
until a point in time has been reached.

As an example: the digest sub-system uses a Scheduler to defer the sending of
the digest message until the configured time to send digests has been reached.


## Glossary

<dl>
  <dt>Scheduler</dt>
  <dd>
    A generic framework for deferring execution of code until a certain point
    in time has been reached.
  </dd>

  <dt>Event Loop</dt>
  <dd>
    A low-level mechanism that the Scheduler uses to defer execution.
  </dd>
</dl>

## Implementation

The Scheduler is an implementation of `\Alaress\Scheduler\AbstractScheduler`.


### Event Loop

The Event Loop is an implementation of `\React\EventLoop\LoopInterface`.
This interface provides the asynchronous equivalent of deferred execution:

```php
$event = new Event();
while (true) {
    if ($event->getWhen() < time()) {
        handleEvent($event);
        break;
    }
    sleep();
}
```

The Scheduler uses the Event Loop to defer execution until the Message is ready
to be processed.
