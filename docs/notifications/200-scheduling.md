[< Back to Notifications](README.md)

# Scheduling

The scheduling subsystem is used to generate messages from events, and queue
those messages to then be sent to recipients.

This subsystem is the bridge between the [event propagator](100-propagation.md)
and the [message router](300-routing.md).


## Sequence Diagram

![Scheduling sequence diagram](scheduling.png "Scheduling sequence diagram")

Source: [scheduling.plantuml](scheduling.plantuml)


## Technical Description and Developer Guide

### Schedule Daemon

The Schedule daemon (`/tools/mq/scheduled.php`) runs a
`\Schoolbox\Realtime\Schedule\Scheduler` instance as its daemon process.

This class is an instance of the [Scheduler](architecture/scheduler.md); like
all Schedulers, it:
* consumes Triggers from an external source
  * in this case, the scheduling
    [Message Broker](architecture/message-broker.md) as published into by the
    event propagator
* sends the Triggers to its [Dispatcher](architecture/dispatcher.md) to be
  processed
* acts as a Response Handler for the response that the Dispatcher returns
  * in this case, it publishes into the routing Message Broker, for the message
    router to handle
