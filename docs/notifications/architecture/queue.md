# Message Queue

The notification system uses message queues to store messages that must be
passed from one process to another.

These queues function as publish-subscribe queues:
* Clients may publish messages to them
* Subscribers may listen for new messages published to a queue, and act on them
  accordingly

Schoolbox provides the `\Alaress\Async\Broker\IBroker` interface for defining
how a message queue must work.


## Glossary

<dl>
  <dt>Message Broker</dt>
  <dd>
    The high-level interface through which Messages are Published and Subscribed
    to.
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

Schoolbox's message queues are implemented in two parts; the message queue
itself, and a data store for the queue.

`\Alaress\Async\Broker\StompRedis\Broker` is the abstract implementation of the
two-part message queue; concrete queues may extend this abstract class.


### Queue

The queue itself is an implementation of the [AMQP](https://www.amqp.org/)
protocol. Schoolbox uses [RabbitMQ](https://www.rabbitmq.com/) as its AMQP
implementation; in theory, it could be switched for a different implementation
without having to modify the interfacing code.

The queue itself stores no data other than keys; the actual message content is
stored in the data store.


### Data Store

Schoolbox message queues use [Redis](https://redis.io/) as a data store.

Each item in the queue contains a key value; this key must be a valid key
within the queue's Redis store.


## Usage


### Publishing

`\Alaress\Async\Broker\IBroker::publish` publishes a message to the message
queue.

The message itself will be `serialize`d before storing it in the data store.

TODO figure out what `$context` does, and exactly what's stored in the queue


### Subscription

`\Alaress\Async\Broker\IBroker::registerConsumer` registers a subscription to
the message queue.

Multiple subscribers may subscribe to a single message queue; the queue knows
about each of its subscribers, and can push messages in a round-robin fashion.


#### Acknowledgement and rejection

The message handler (as passed to `registerConsumer` above) must implement
`\Alaress\Async\Broker\IConsumer`; this interface prescribes `onMessage` and
`onException` methods.

`onMessage` receives a message from the queue; it's up to the consumer to
figure out what to do with it, and then either acknowledge or reject it using
the `\Alaress\Async\Broker\IResolver` this message receives.


The message queue holds messages in escrow when consumed by a consumer:
* Acknowledging the message will cause the queue to remove the message from
  the queue
* Rejecting the message will cause the queue to re-queue the message
* If the handler process terminates unexpectedly, the queue will be notified;
  this will cause the queue to re-queue any messages that were held by the
  handler.
