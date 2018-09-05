[< Back to Notifications](README.md)

# Routing

The routing subsystem is used to determine to whom, and by what channel, a
message is to be delivered.

This subsystem is the bridge between the [scheduler](200-scheduling.md) and the
[delivery system](400-delivery.md).

## Sequence Diagram

![Routing sequence diagram](routing.png "Routing sequence diagram")

Source: [routing.plantuml](routing.plantuml)

## Technical Description and Developer Guide

### Router Daemon

The Router daemon (`/tools/notif/route/routed.php`) runs a
`\Schoolbox\Realtime\Routing\Subscription\Handler` instance as its daemon
process.

This class is an instance of the [Scheduler](architecture/scheduler.md); like
all Schedulers, it:
* consumes Triggers from an external source
  * in this case, the scheduling
    [Message Broker](architecture/message-broker.md) as published into by the
    event propagator
* sends the Triggers to its [Dispatcher](architecture/dispatcher.md) to be
  processed
  * in this case, the Trigger is a call to a `notify` method on a
    `\Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface` instance
* acts as a Response Handler for the response that the Dispatcher returns
  * in this case, it publishes into the routing Message Broker, for the message
    router to handle


### Resolver

[< Scheduling sub-system documentation](200-scheduling.md) |
[Delivery sub-system documentation >](400-delivery.md)
