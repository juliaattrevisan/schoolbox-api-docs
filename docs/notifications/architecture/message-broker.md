# Message Broker

The notification system uses message brokers to store messages that must be
passed from one process to another.

The message broker functions as a publish-consume queue would:
* Clients may publish messages to them
* Consumers may listen for new messages published to a them, and act on the
  message content accordingly
* Once a Consumer has acted upon a message, it is removed from the queue

Schoolbox provides the `\Alaress\Async\Broker\IBroker` interface for defining
how a message broker must work.

> TODO: class/object diagrams?


## Glossary

<dl>
  <dt>Message Broker</dt>
  <dd>
    The high-level interface through which Messages are Published to and
    Consumed from.
  </dd>

  <dt>AMQP</dt>
  <dd>
    Advanced Message Queueing Protocol: the protocol that the Message Broker
    uses for publishing messages.
  </dd>

  <dt>STOMP</dt>
  <dd>
    Simple/Streaming Text Oriented Messaging Protocol: the protocol that the
    Message Broker uses for registering a Consumer.
  </dd>

  <dt>RabbitMQ</dt>
  <dd>
    The low-level implementation of message broking that the Schoolbox Message
    Broker uses as its AMQP and STOMP implementation.
  </dd>

  <dt>Event Loop</dt>
  <dd>
    A low-level mechanism that the STOMP protocol implementation uses to
    asynchronously listen for published Messages.
  </dd>

  <dt>Queue</dt>
  <dd>
    The low-level implementation of a message queue that the Message Broker
    uses.
  </dd>

  <dt>Data Store</dt>
  <dd>
    The low-level storage mechanism for Messages that have been Published to
    the Message Broker.
  </dd>

  <dt>Message</dt>
  <dd>
    A payload that is Published to the Message Broker. Any Consumer will see
    the payload, and be able to act upon it.
  </dd>

  <dt>Publish</dt>
  <dd>
    The act of a process pushing a Message to the Message Broker.
  </dd>

  <dt>Consumption</dt>
  <dd>
    The act of a process receiving a Message from the Message Broker.
  </dd>
</dl>


## Implementation

Schoolbox's message brokers are implemented in two parts; the message queue
itself, and a data store for the queue.

`\Alaress\Async\Broker\StompRedis\Broker` is the abstract implementation of the
two-part message broker; concrete brokers may extend this abstract class.


### Queue

Schoolbox uses [RabbitMQ](https://www.rabbitmq.com/) as its AMQP and STOMP
implementation; in theory, it could be switched for a different implementation
without having to modify the interfacing code.

The queue itself stores no data other than keys; the actual message payload is
stored in the data store.


#### AMQP

Schoolbox Message Queues use [AMQP](https://www.amqp.org/) as the protocol for
publishing messages.

Schoolbox uses an implementation of `\Alaress\Async\AMQP\AMQPInterface` for its
Message Queue Publishers.


#### STOMP

Schoolbox Message Queues use [STOMP](https://stomp.github.io/) as the protocol
for registering a Consumer.

Schoolbox uses an instance of `\React\Stomp\Client` for its Message Queue
Consumers.


#### Why two separate protocols?

AMQP is best suited for publishing, because it has the ability to send to a
particular exchange.

> Currently, all Message Brokers within Schoolbox send to the default exchange.
  This is subject to change as Message Brokers may start to live on different
  servers to other Schoolbox services.

STOMP is best suited for consumers, because of its asynchronicity: the STOMP
client library Schoolbox uses makes use of the Event Loop so that consumers do
not block.

RabbitMQ is able to understand both protocols, and can translate a message
published via AMQP into STOMP when a Consumer receives it.


### Data Store

Schoolbox message brokers use [Redis](https://redis.io/) as a data store.

Each item in the queue contains a key value; this key must be a valid key
within the data store, otherwise the queue item is considered to be invalid
and will be removed from the queue.


### Why a two-part implementation?

#### Why not just RabbitMQ?

An earlier implementation of the notification system used RabbitMQ as both the
queue and the data store.

This resulted in `count(users) * 4` queues for each user; for schools with
thousands of users this caused significant performance issues, as RabbitMQ is
designed to be most performant and efficient with a small number of
high-throughput queues.

In addition, due to performance issues with persisting and reloading RabbitMQ
instances with large numbers of queues, the decision was made to no longer
treat RabbitMQ as the single source of truth for queue data.

The current implementation of notifications maintains just two queues: one for
the [scheduling](../200-scheduling.md) sub-system, and one for
[routing](../300-routing.md).


#### Why not just Redis?

Redis implements [publish/subscribe messaging](https://redis.io/topics/pubsub);
the reason that notifications don't use Redis alone as the message broker is
that it doesn't support the extra functionality that a publish/consume queue
requires. In particular, Redis publish/subscribe queues have no support for
message escrow/acknowledgement/rejection, which are essential for ensuring that
a message is processed once and only once.

Redis publish/subscribe queues are used in other parts of notifications; for
example, daemons use them for storing messages received by tasks.


## Usage


### Publishing

`\Alaress\Async\Broker\IBroker::publish` publishes a message to the message
broker.

The message itself will be `serialize`d before storing it in the data store.

`publish` accepts an optional `context` parameter; if this is passed, it is
received as part of a consumer's `onMessage` method call. (What a consumer
actually does with the `context` if present is up to each individual
consumer.)


### Consumption

`\Alaress\Async\Broker\IBroker::registerConsumer` registers a consumer of the
message broker.

The consumer must be an instance of `\Alaress\Async\Broker\IConsumer`; its
`onMessage` method will be called when it receives a message.

Multiple consumers may consume from a single message broker; the underlying
queue knows about each of its consumers, and will push messages in round-robin
fashion.


#### Acknowledgement and rejection

The message handler (as passed to `registerConsumer` above) must implement
`\Alaress\Async\Broker\IConsumer`; this interface prescribes `onMessage` and
`onException` methods.

`onMessage` receives a message from the broker; it's up to the consumer to
figure out what to do with it, and then either acknowledge or reject it using
the `\Alaress\Async\Broker\IResolver` this message receives.

The underlying queue holds messages in escrow when consumed by a consumer:
* Acknowledging the message will cause the queue to remove the message
* Rejecting the message will cause the queue to re-queue the message
* If the handler process terminates unexpectedly, the queue will be notified;
  this will cause the queue to re-queue any messages that were held by the
  handler.
