# Daemon

The notification system uses daemon processes for listening to events within
Schoolbox, and then performing actions based on these changes.

Generally, a daemon listens by subscribing to a message broker; when it
receives an event from the broker, it will summon a worker process to perform a
task.


## Glossary

<dl>
  <dt>Daemon</dt>
  <dd>
    A long-running process which listens to events, and acts upon those events.
  </dd>

  <dt>Message Broker</dt>
  <dd>
    The high-level interface through which Messages are Published and
    Subscribed to.
  </dd>

  <dt>Scheduler</dt>
  <dd>
    The interface through which a Daemon can Dispatch a Task to be executed,
    and which handles what happens when a Task returns a Response.
  </dd>

  <dt>Dispatcher</dt>
  <dd>
    The interface through which a Scheduler assigns Tasks to Workers.
  </dd>

  <dt>Worker Pool</dt>
  <dd>
    A collection of pre-created Workers waiting to be assigned a Task.
  </dd>

  <dt>Worker</dt>
  <dd>
    A child process of the Daemon, summoned only to perform Tasks.
  </dd>

  <dt>Task</dt>
  <dd>
    The job that a Worker has been assigned.
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

  <dt>Signal Queue</dt>
  <dd>
    A queue which Daemons subscribe to, for the purpose of receiving control
    signals.
  </dd>

  <dt>Inter-Process Communication (IPC) Protocol</dt>
  <dd>
    The protocol through which processes (in the daemon's case, the Dispatcher
    and the Tasks it creates) communicate with one another.
  </dd>
</dl>


## Implementation

Schoolbox Daemons are implemented as a collection of parts:
* Message Broker Subscription
* Scheduler (optional)
* Dispatcher
* Response Handler


### Message Broker Subscription

The Message Broker is an implementation of `\Alaress\Async\Broker\IBroker`.
(Further documentation on Message Brokers may be found in the
[Message Broker documentation](message-broker.md).)

Schoolbox Daemons contain a Subscription to a Message Broker; when that
Subscription receives a Message, the Daemon uses that Message as a signal to
Dispatch a Task.

Once the Task has finished, the Daemon either:
* acknowledges the Message if the Task completed successfully
  * this removes the Message from the Broker
* re-Publishes the Message if the Task failed to complete successfully
  * this allows the Message to be handled again by the same Daemon, thus
    allowing the Daemon to try again in the case of transient failures

Then, the Response is sent to the Response Handler to act upon; the action
that the Response Handler takes is implementation-specific.


### Scheduler

The Scheduler is an optional component of a Daemon. (Further documentation on
the Scheduler may be found in the [Scheduler documentation](scheduler.md).)

If it exists, is responsible for determining _when_ a Task is dispatched;
depending on the payload of the Message received from the Message Broker, it
will either:
* send the Task associated with the Message to the Dispatcher immediately
* defer Dispatch until the payload's trigger time is reached

If the Daemon doesn't contain a Scheduler, it will simply send the task to the
Dispatcher immediately.


### Dispatcher

### Worker Pool

### Worker

### Task

### Response Handler

The Response Handler is an implementation of
`\Alaress\WorkerCrew\TaskResponseHandlerInterface`.

Once a Task has returned a Response, the Response Handler is responsible for
acting upon the content of that Response; it is handled in the implementation's
`handleResponse` method.


### Signal Queue

The Signal Queue is implemented as a Redis publish/subscribe queue; its handler
must implement `\Alaress\Redis\Async\EventListener`, and handle the signal in
its `handle` method.

It exists primarily so that processes can broadcast a
[control signal](https://en.wikipedia.org/wiki/Signal_(IPC)#POSIX_signals)
to all instances of the same daemon (either global, or restricted to a single
Schoolbox instance), without having to know the exact identity of every daemon.

In practise, the Signal Queue's handler is often the same object as the
Response Handler; this is because both handlers must often act upon the same
set of objects.


## Usage
