# Schoolbox Task Daemon

This namespace only contains code which Schoolbox requires for communicating
with the task daemon.

The core task daemon code, and developer instructions, may be found in the task
daemon repository: https://github.com/alaress/taskd

Developers only need to reference the above repository if they are going to
modify the task daemon code: otherwise, Schoolbox will use a pre-built image to
build the task daemon container.
