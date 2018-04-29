# Dispatcher

The notification system Daemons use the Dispatcher as the interface through
which their Scheduler assigns Tasks to Workers, and receives a Response from
them once they have completed execution.

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

  <dt>Event Loop</dt>
  <dd>
    A component used for asynchronously listening for events.
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

The Dispatcher is an implementation of
`\Alaress\WorkerCrew\DispatcherInterface`.

> TODO figure out how a Task's output gets back to the Daemon

At a high level, it is responsible for Tasks being performed. The
implementation that Daemons use uses Workers to perform Tasks, so that:
* the Daemon is not blocked whilst running the Task
* the Daemon is not brought down by a Task run which results in an
  unrecoverable error

The `enqueue` method enqueues a Task to be Dispatched. This method's parameters
are arbitrary in their specifics, but will always be:
* an identifier: an object on which a command will run
* a command: a string, which maps to a command to process
* data: an array of values the command will require

> Note that `enqueue`'s parameters match the parameters for:
>  * the Response Handler's `handleResponse` method
>  * the Worker Pool's `accept` method
>  * the Worker's `setTask` method
>  * the Task's constructor

The `dispatch` method dispatches the most recently added Task.
The Event Loop triggers this method on a regular basis.

Depending on the Tasks that the Worker Pool is able to perform (see
`\Alaress\WorkerCrew\Pooling\DispatchStrategyInterface::getSupportedActions`),
`dispatch` will either run the Task immediately or send it to the Worker Pool.


### Dispatch Strategy

The Dispatch Strategy is an implementation of
`\Alaress\WorkerCrew\Pooling\DispatchStrategyInterface`.

It is responsible for managing various aspects of how the Dispatcher runs; at
this stage, we're most interested in its `getSupportedActions` method, as it
determines whether the Dispatcher runs a Task immediately, or sends it to the
Worker Pool.


### Event Loop

The Event Loop is an implementation of `\React\EventLoop\LoopInterface`.
Think of it as the asynchronous equivalent of a classic event loop:

```php
$eventQueue = new EventQueueSubscription();
while (true) {
    foreach ($eventQueue->getEvents() as $event) {
        handleEvent($event);
    }
}
```

It is used by the Dispatcher to defer execution of a Task until its time to
execute is reached.


### Worker Pool

The Worker Pool is a strategy for the assignment of Tasks to Workers. It is an
implementation of `\Alaress\WorkerCrew\PoolInterface`.

It ensures that only a certain number or Worker processes exist, and that
Workers remain alive and contactable after they have finished a Task so that
they may be reused (thus avoiding unnecessary overhead involved with spawning a
new Worker process).

It is responsible for:
* answering a request for a Worker to perform a Task, either with:
  * a [process identifier](https://en.wikipedia.org/wiki/Process_identifier),
    corresponding to the process identifier of the Worker assigned the Task
  * a rejection of the request if the maximum number of Workers is reached,
    and none of them are idle
* listening for when a Worker's Task sends a message
* monitoring the health of its Workers
  * keeping them alive and contactable after they have finished a Task
  * releasing a Worker (by sending a `SIGTERM` or `SIGKILL` signal) if it has
    been busy too long
  * releasing a Worker process if it has been idle for too long

The pool's `accept` method is used to trigger a Task. Any messages the Task
sends are captured by the Pool's `dataReceived` method; this is able to then
trigger the Response Handler's `handleResponse` with the response received from
the Task.


### Stream Listener

The Stream Listener is an implementation of
`\Alaress\Async\Listener\Stream\StreamListener`.

The Worker Pool uses this to listen on messages received from its Workers.


### Worker

A Worker is an abstraction of a child process of the Daemon. It is an
instance of `\Alaress\WorkerCrew\Worker\Worker`.

The Worker itself is internal to the Worker Pool and Dispatcher; anything
outside of these classes need not know about this abstraction.


### Task

A Task is run within a Worker.

> TODO figure out how a call to `enqueue` actually becomes a Task callback

The Task is able to receive data from its input pipe, and send data through its
various output pipes (output, log, error) to its encapsulating Worker process.

`\Alaress\WorkerCrew\Worker\IO\Listener\DataIOListener::dataReceived` receives
messages through this pipe; once it receives a message the the Task has
completed, the Response Handler's `handleResponse` method gets called.


### IPC Protocol

The protocol used to transmit information to and from the Task encapsulated by
the Worker.

It is an implementation of `\Alaress\WorkerCrew\IPC\ProtocolInterface`; it is
responsible for:
* constituting data sent to the Worker's input pipe, so that the its Task may
  act upon it
* re-constituting data sent by the Worker's output pipe, so that the Dispatcher
  may receive its output, eventually sending to the Response
