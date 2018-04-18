# Message Broker

The notification system uses message brokers to store messages that must be
passed from one process to another.

The message broker functions as a publish-subscribe queue would:
* Clients may publish messages to them
* Subscribers may listen for new messages published to a them, and act on the
  message content accordingly

Schoolbox provides the `\Alaress\Async\Broker\IBroker` interface for defining
how a message broker must work.


## Glossary

<dl>
  <dt>Message Broker</dt>
  <dd>
    The high-level interface through which Messages are Published and
    Subscribed to.
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
    A payload that is Published to the Message Broker. Any Subscriber will see
    the payload, and be able to act upon it.
  </dd>

  <dt>Publish</dt>
  <dd>
    The act of a process pushing a Message to the Message Broker.
  </dd>

  <dt>Subscribe</dt>
  <dd>
    The act of a process receiving a Message to the Message Broker.
  </dd>
</dl>


## Implementation

Schoolbox's message brokers are implemented in two parts; the message queue
itself, and a data store for the queue.

`\Alaress\Async\Broker\StompRedis\Broker` is the abstract implementation of the
two-part message broker; concrete brokers may extend this abstract class.


### Queue

The queue itself is an implementation of the [AMQP](https://www.amqp.org/)
protocol. Schoolbox uses [RabbitMQ](https://www.rabbitmq.com/) as its AMQP
implementation; in theory, it could be switched for a different implementation
without having to modify the interfacing code.

The queue itself stores no data other than keys; the actual message payload is
stored in the data store.


### Data Store

Schoolbox message brokers use [Redis](https://redis.io/) as a data store.

Each item in the queue contains a key value; this key must be a valid key
within the data store, otherwise the queue item is considered to be invalid
and will be removed from the queue.


### Why a two-part implementation?

#### Why not just AMQP?

An earlier implementation of the notification system used RabbitMQ as both the
queue and the data store.

This resulted in `count(users) * 4` queues for each user; for schools with
thousands of users this caused significant performance issues, as RabbitMQ is
designed to be most performant and efficient with a small number of
high-throughput queues.

> TODO figure out what the actual queues used are

The current implementation of notifications maintains just two queues: one for
the [scheduling](../200-scheduling.md) sub-system, and one for
[routing](../300-routing.md).


#### Why not just Redis?

Redis implements [publish/subscribe messaging](https://redis.io/topics/pubsub);
the reason that notifications don't use Redis alone as the message broker is
that it doesn't support message escrow/acknowledgement/rejection, which are
essential for ensuring that a message is processed once and only once.

> TODO determine the veracity of the above statement

Redis publish/subscribe queues are used in other parts of notifications; for
example, daemons use them for storing messages received by tasks.


## Usage


### Publishing

`\Alaress\Async\Broker\IBroker::publish` publishes a message to the message
broker.

The message itself will be `serialize`d before storing it in the data store.

TODO figure out what `$context` does, and exactly what's stored in the queue


### Subscription

`\Alaress\Async\Broker\IBroker::registerConsumer` registers a subscription to
the message broker.

Multiple subscribers may subscribe to a single message broker; the underlying
queue knows about each of its subscribers, and can push messages in a
round-robin fashion.


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
