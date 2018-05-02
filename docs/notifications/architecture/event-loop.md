# Event Loop

The Event Loop is the low-level mechanism used throughout the Schoolbox
notification system for asynchronous execution of timed events.


## Implementation

The Event Loop is an implementation of `\React\EventLoop\LoopInterface`.
Its documentation may be found online at
[https://reactphp.org/event-loop/](https://reactphp.org/event-loop/)


## Usage

The notification system uses the Event Loop for its periodic timers and to
defer execution of a callback until the time is right to process it.


## Periodic Timer Loop

The Event Loop may be used as a periodic timer loop, within which events are
listened for and handled asynchronously.

```php
$eventQueue = new EventQueueSubscription();
$timer      = $loop->addPeriodicTimer($interval, function () use ($eventQueue) {
    foreach ($eventQueue->getEvents() as $event) {
        handleEvent($event);
    }
});
```


## Deferred Execution

The Event Loop may also be used to provide asynchronous deferred execution.

```php
$event = new Event();
$timer = $loop->addTimer($delay, function () use ($event) {
    handleEvent($event);
});
```
