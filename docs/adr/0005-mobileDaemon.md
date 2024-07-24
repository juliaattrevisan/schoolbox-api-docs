# Mobile Daemon

* Status: Proposed
* Deciders: Amri Sannang
* Date: 2024-06-26

Technical Story: https://schoolbox.atlassian.net/browse/SA-17997,
https://schoolbox.atlassian.net/browse/SA-17786


## Context and Problem Statement

Several customers have reported that notifications which are intended to be sent
to mobile devices have instead been lost seemingly at random.

Support has determined that the main indicator of lost notifications is the
`scheduled` log reporting that Digistorm (a mobile app provider which Schoolbox
may send notifications through) is rate-limiting the Schoolbox application.

It is believed that the retry after rate-limit is what causes notifications to
be lost:
- `scheduled` has already acknowledged the message coming through to its queue:
  it will not be re-processed
- the retry happens in the worker which forwards notifications, and the retry
  sends the worker to sleep
  - this causes the controlling process to believe that the worker is
    unresponsive and thus able to be terminated
- since `scheduled` thinks the message is acknowledged, and the worker process
  sending the message is terminated, the notification will never be sent

https://schoolbox.atlassian.net/browse/SA-17786 has already been attempted as a
solution: it replaced a blocking `usleep` call with an asynchronous ReactPHP
timer. However, this did not stop the reports of lost notifications.


## Decision Drivers

- Traceability: there needs to be visibility into what is happening with the
  delivery process
- Recoverability: in the event that the notification cannot be delivered, or the
  process delivering the notification fails, the notification should be
  delivered at a later time
- Expedience: missing notifications are considered to be a critical issue, thus
  a solution is required sooner rather than later


## Considered Options

With all these options, the worker process is no longer responsible for retrying
sending of notifications to the mobile app provider.

### Worker sends a retry message back to schedule daemon

The worker is able to report a new status back to the schedule daemon: it can
currently report that it is:
- `ready` for its first task
- `busy` with another task
- `done` with its task, and ready for another task
- `ended`, and not accepting tasks
- in an `error` state, and thus not accepting tasks

This new status would signal that the task must be retried if the app provider
is rate limited: the schedule daemon would then need to schedule a retry.

### Push mobile notifications to a simple queue

Instead of attempting to send a notification directly, the worker can push
mobile notifications to a queue for handling by another process.

This queue would be a simple Redis queue, just as the existing queues for
in-browser notifications are.

### Push mobile notifications to a brokered queue

Another type of queue is the
[brokered queue](../notifications/architecture/message-broker.md): this allows
as close to exactly-once processing of messages as is feasible.

## Decision Outcome

I've chosen to push mobile notifications to a brokered queue, as this provides
the most reliable method for ensuring that a notification is published.

## Pros and Cons of the Options

### Worker sends a retry message back to schedule daemon

#### Pros
- few changes required to worker itself

#### Cons
- need to update worker pool to recognise that a task must be retried: this
  necessitates a significant extension of the worker pool

### Push mobile notifications to a simple queue

#### Pros
- queue classes already exist (`\Schoolbox\Realtime\MessageList\AbstractList`),
  can simply make the mobile queue an instance of this class

#### Cons
- another process is required to consume these messages
- these queues are per-user, not per-instance: all of these queues require a
  subscriber
- these queues do not support message acknowledgement
- messages sent to these queues are irrecoverable

### Push mobile notifications to a brokered queue

#### Pros
- brokered queue classes (`\Alaress\Async\Broker\StompRedis\Broker`) and
  consumers (`\Alaress\Scheduler\AbstractScheduler`) already exist
- these queues support acknowledgement
- these queues support recovery, as their data is backed by Redis

#### Cons
- another process is required to consume these messages
