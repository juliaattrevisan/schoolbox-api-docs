[< Back to Notifications](README.md)

# Routing

The routing subsystem is used to determine via which channel(s) a message is to
be delivered.

This subsystem is the bridge between the [scheduling](200-scheduling.md) and
the [delivery](400-delivery.md) subsystems.

## Sequence Diagram

![Routing sequence diagram](routing.png "Routing sequence diagram")

Source: [routing.plantuml](routing.plantuml)

## Technical Description and Developer Guide

### Router Process

Message routing is handled in a separate worker process, invoked by the
[Scheduling sub-system](200-scheduling.md).


### Queue Sorter

The Router worker process has received the audience of the message: the Queue
Sorter within the worker determines the mechanism via which the message is
delivered.

It queries the database to determine the configured delivery mechanisms for
that message topic, and then adds the message to the queue for the appropriate
sub-section of the [delivery sub-system](400-delivery.md).


[< Scheduling sub-system documentation](200-scheduling.md) |
[Delivery sub-system documentation >](400-delivery.md)
