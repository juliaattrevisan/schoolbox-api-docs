[< Back to Notifications](README.md)

# Delivery

The delivery subsystem is used to send a message to its final destination, in
the form of a notification.

* The propagation subsystem has determined when the message goes live
* The scheduling subsystem has determined the content of the message, and the
  channel on which it's broadcast
* The routing subsystem has determined the audience that is subscribed to the
  message's channel, and the mechanism of delivery


## Messages vs. Notifications

A notification is simply one or more messages, compiled together into an
end-user digestible format and delivered to a user.


## Mechanisms

The delivery subsystem consists of distinct delivery mechanisms, each of which
delivers notifications via a different process.


### In-Browser Instant Notifications

Messages may be delivered by instant notification within a Schoolbox window.
These messages are not browser notifications (should that be implemented?), but
instead render in the notifications side panel.

The instant notification subsystem is implemented as a WebSockets server, for
full-duplex communication between the Schoolbox server and the web browser.

> TODO document instant notifications


### Email

As an addition to instant notification, users may opt to additionally receive
an email copy of any notifications that they receive.

The email subsystem is simply a mail queue; a process monitors the queue, and
sends emails to recipients accordingly.


### Schoolbox App

As an addition to instant notification, users who have installed the Schoolbox
app for their school may opt to additionally receive notifications directly
through the app.

The app subsystem is simply a queue; a process monitors the queue, and pushes
notifications to those who have subscribed to the app. The app then issues an
OS-native notification.

> TODO document app messaging


### Digest

If a user does not wish to receive instant notifications on a particular
channel, they may instead subscribe to the digest.

The digest subsystem queues all messages, until the configured send time for a
role has been reached; then, every message in the digest queue for that role
will be read and rendered into a single notifications, which is then delivered
via email.

> TODO document digest


### Mute

If a user does not wish to receive notifications for messages on a particular
channel at all, they may opt to mute the channel.

No notifications will be delivered for messages on a muted channel.


[< Routing sub-system documentation](300-routing.md)
