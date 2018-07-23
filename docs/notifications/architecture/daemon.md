# Daemon

The notification system uses daemon processes for listening to events within
Schoolbox, and then performing actions based on these changes.

Generally, a daemon listens by subscribing to a message broker; when it
receives an event from the broker, it will summon a worker process to perform a
task.

> TODO: sequence/class/object diagrams?


## Glossary

<dl>
  <dt>Daemon</dt>
  <dd>
    A long-running process which listens to events, and acts upon those events.
  </dd>

  <dt>Event Listener</dt>
  <dd>
    The mechanism through which the Daemon listens for external events, and
    then prompts the Daemon to act.
  </dd>

  <dt>Message Broker</dt>
  <dd>
    The high-level interface through which Messages are Published and
    Subscribed to.
  </dd>

  <dt>Scheduler</dt>
  <dd>
    The interface through which a Daemon can hold a Task in a waiting area to
    await execution, and which handles what happens when a Task returns a
    Response.
  </dd>

  <dt>Dispatcher</dt>
  <dd>
    The interface through which a Scheduler assigns Tasks to be completed.
  </dd>

  <dt>Task</dt>
  <dd>
    The job that the Dispatcher has been assigned to complete.
  </dd>

  <dt>Response</dt>
  <dd>
    The output of a Task, once it has completed execution.
  </dd>

  <dt>Response Handler</dt>
  <dd>
    The object which listens for Responses, and then performs an action based
    on the Response.
  </dd>

  <dt>Signal Listener</dt>
  <dd>
    A mechanism through which Daemons listen for control signals.
  </dd>
</dl>


## Implementation

Schoolbox Daemons are implemented as a collection of parts:
* Event Listener
* Scheduler (optional)
* Dispatcher
* Response Handler


### Event Listener

The Event Listener need not be an implementation of any particular class or
interface, but it must at least be something that knows about events that
happen outside of the Daemon.

Daemons currently use one of the following as their Event Listener (but are not
restricted to using either):
* Message Broker Subscription
* HTTP Web Socket Server


#### Message Broker Subscription

The Message Broker is an implementation of `\Alaress\Async\Broker\IBroker`.
(Further documentation on Message Brokers may be found in the
[Message Broker documentation](message-broker.md).)

Schoolbox Daemons contain a Subscription to a Message Broker; when that
Subscription receives a Message, the Daemon uses that Message as a signal to
Dispatch a Task.

> As discussed in the Message Broker documentation, the Subscription is an
  instance of `\Alaress\Async\Broker\IConsumer`; its `onMessage` method will be
  called when it receives a message, and that is the point where the Task will
  be Dispatched.
>
> `onMessage` will call the Dispatcher's `enqueue` method, either directly or
  via a Scheduler.

Once the Task has finished, the Daemon either:
* acknowledges the Message if the Task completed successfully
  * this removes the Message from the Broker
* re-Publishes the Message if the Task failed to complete successfully
  * this allows the Message to be handled again by the same Daemon, thus
    allowing the Daemon to try again in the case of transient failures

Then, the Response is sent to the Response Handler to act upon; the action
that the Response Handler takes is implementation-specific.


#### HTTP Web Socket Server

The HTTP Web Socket Server within a Schoolbox Daemon functions as any other
web socket server would:
* it listens to connection requests on a particular port
* once a client has connected, it may either:
  * receive further requests from the client, if an event occurs which the
    client should know about
  * send responses to the client, if the client performs an action which the
    daemon should know about

When the HTTP request is received, the Daemon uses the request as a signal to
Dispatch a Task (just as it does when the Daemon contains a Message Broker
Subscription).

Once the Task has finished, the Response Handler is responsible for performing
an action; again, the action that the Response Handler takes is
implementation-specific.

The Web Sockets Server may also listen to other sources of events (for example,
a Message Broker Subscription), and from those events generate and send a
response to the client.


### Scheduler

The Scheduler is an optional component of a Daemon.

If it exists, is responsible for determining _when_ a Task is dispatched;
depending on the payload of the Message received from the Message Broker, it
will either:
* send the Task associated with the Message to the Dispatcher immediately
* defer Dispatch until the payload's trigger time is reached

If the Daemon doesn't contain a Scheduler, it will simply send the task to the
Dispatcher immediately.

Further information on the Scheduler may be found in the
[Scheduler documentation](scheduler.md).


### Dispatcher

The Dispatcher is an implementation of
`\Alaress\WorkerCrew\DispatcherInterface`.

The Dispatcher implementation that Daemons use ensures that:
* the Daemon is not blocked whilst running the Task
* the Daemon is not brought down by a Task run which results in an
  unrecoverable error

Further information on the Dispatcher may be found in the
[Dispatcher documentation](dispatcher.md).


### Response Handler

The Response Handler is an implementation of
`\Alaress\WorkerCrew\TaskResponseHandlerInterface`.

Once a Task has returned a Response, the Response Handler is responsible for
acting upon the content of that Response; it is handled in the implementation's
`handleResponse` method.

> In all current Daemon implementations, the Response Handler is the same
  object as the Event Listener. This means that the object has the dual
  responsibility of listening to incoming messages, and acting upon the content
  of any Tasks that were executed as a result.


### Signal Listener

The Signal Listener is implemented as a Redis publish/subscribe queue; its
handler must implement `\Alaress\Redis\Async\EventListener`, and handle the
signal in its `handle` method.

It exists primarily so that processes can broadcast a
[control signal](https://en.wikipedia.org/wiki/Signal_(IPC)#POSIX_signals)
to all instances of the same daemon (either global, or restricted to a single
Schoolbox instance), without having to know the exact identity of every daemon.

In practise, the Signal Listener's handler is often the same object as the
Response Handler; this is because both handlers must often act upon the same
set of objects.


## Usage
