# Kernel

The Kernel component performs bootstrapping of all incoming requests into
Schoolbox.

All kernels have the following responsibilities, as seen in
`\Alaress\Application\Kernel::__construct`:
* initialize connections to databases (`initialiseExternalConnections`)
    * (may not be required anymore, because a connection was created in
      [instance configuration](../configuration/instance-configuration.md))
* register error, exception and shutdown handling
* set up dependency injection
* set up dependencies which are only configurable after dependency injection
  is complete
    * currently sets up sessions, routing table and modules: may not be required
      as all this could be done in DI above

## Variants

- [Web](web/README.md)
    - [Standard](web/standard.md)
    - [Basic](web/basic.md)
- CLI
- Daemon

### Web Request

This is the standard web request kernel: it configures all things which a web
request may need.

### Basic Web Request

This web request is designed for when there is no user interface to display.

It is the same as the standard web request kernel, except its dependency
injection container does not set up UI dependencies (e.g. Twig).

> This may become obsolete: the original reasoning for the basic web request
> kernel (performance) is less relevant now that the routing table and module
> configuration are cached.

### CLI

The CLI kernel is designed to handle command-line requests: in addition to the
standard web request kernel, it is able to parse command-line options.

### Daemon

The daemon kernel is a variant of the CLI kernel, designed for the specific
requirements of daemons (long-running background processes, which listen to
various input streams).

In addition to the CLI kernel's responsibilities, it is able to:
* create a loop for running code asynchronously
* handle errors in a manner which allows for recovery
