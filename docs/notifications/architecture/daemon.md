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

  <dt>Inter-Process Communication (IPC) Protocol</dt>
  <dd>
    The protocol through which processes (in the daemon's case, the Dispatcher
    and the Tasks it creates) communicate with one another.
  </dd>
</dl>


## Implementation

Schoolbox Daemons are implemented as a collection of parts:
* Message Broker Subscription
* Scheduler
* Dispatcher
* Redis Queue subscription?
  * TODO figure this part out


### Message Broker Subscription

The Message Broker is an implementation of `\Alaress\Async\Broker\IBroker`.
(Further documentation on Message Brokers may be found in the
[Message Broker documentation](message-broker.md).)

Schoolbox Daemons contain a Subscription to a Message Broker; when that
Subscription receives a Message, the Daemon uses that Message as a signal to
Schedule a Task.

Once the Task has finished, the Daemon either:
* acknowledges the Message if the Task completed successfully
  * this removes the Message from the Broker
* re-Publishes the Message if the Task failed to complete successfully
  * this allows the Message to be handled again by the same Daemon, thus
    allowing the Daemon to try again in the case of transient failures

A Daemon may also (depending on its intended functionality) Publish Messages to
another Message Broker. It seems to do this via a Redis queue subscription?
(TODO figure that part out)


### Scheduler

The Scheduler is an implementation of `\Alaress\Scheduler\AbstractScheduler`.

It receives Messages from the Message Broker, and depending on the payload:
* sends the Task associated with the Message to the Dispatcher immediately
* defers Dispatch until the payload's trigger time is reached

Once a Task has returned a Response, the Scheduler is also responsible for
acting upon the content of that Response.
* TODO this is actually responsibility of `\Alaress\Redis\Async\EventListener`,
  which the schedule daemon's scheduler _happens to_ implement


#### Event Loop

The Event Loop is the low-level mechanism that the Scheduler uses to defer
sending of a Task to the Dispatcher. It is an implementation of
`\React\EventLoop\LoopInterface`.

TODO figure out how React loop fits into this


### Dispatcher

### Worker Pool

### Worker

### Task

## Usage

TODO discuss how the various parts of daemons work
