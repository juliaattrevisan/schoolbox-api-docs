# Daemon Kernel

The daemon kernel is a variant of the [CLI kernel](cli.md), designed for the
specific requirements of daemons.

In addition to the CLI kernel's responsibilities, it is able to:
- create a loop for running code asynchronously
- handle errors in a manner which allows for recovery, or a graceful exit so
  that the host operating system's service manager
  (e.g. [systemd](https://systemd.io/)) may restart the process
