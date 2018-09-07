[< Back to Notifications](README.md)

# Delivery

The delivery subsystem is used to send a message to its final destination.

* The propagation subsystem has determined when the message goes live
* The scheduling subsystem has determined the content of the message, and the
  channel on which it's broadcast
* The routing subsystem has determined the audience that is subscribed to the
  message's channel, and the mechanism of delivery


## Mechanisms

The delivery subsystem consist of distinct delivery mechanisms, each of which
delivers messages via a different process.


### In-Browser Instant Notifications

Messages may be delivered by instant message within a Schoolbox window. These
messages are not browser notifications (should that be implemented?), but
instead render in the notifications side panel.

The instant messaging subsystem is implemented as a WebSockets server, for
full-duplex communication between the Schoolbox server and the web browser.

> TODO document instant notifications


### Email

As an addition to instant messaging, users may opt to additionally receive
an email copy of any messages that they receive.

The email subsystem is simply a mail queue; a process monitors the queue, and
sends emails to recipients accordingly.


### Schoolbox App

As an addition to instant messaging, users who have installed the Schoolbox app
for their school may opt to additionally receive messages directly through the
app.

The app subsystem is simply a queue; a process monitors the queue, and pushes
notifications to those who have subscribed to the app. The app then issues an
OS-native notification.

> TODO document app messaging


### Message Digest

If a user does not wish to receive instant messages on a particular channel,
they may instead subscribe to the message digest.

The message digest subsystem queues all messages, until the configured send
time for a role has been reached; then, every message in the digest queue for
that role will be read and rendered into an email.

> TODO document digest


### Mute

If a user does not wish to receive messages on a particular channel at all,
they may opt to mute the channel.

No messages will be delivered on a muted channel.


[< Routing sub-system documentation](300-routing.md)
