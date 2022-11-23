# Web Kernel

The Web Kernel handles all incoming HTTP requests to Schoolbox, once the
relevant instance has been determined and its configuration has been loaded.

The entrypoint for all web kernel handled requests is `www/index.php`.

A different web kernel variant may be created depending on the request's path:
* if the path ends with `.php` _and_ exists in Schoolbox's `www/` folder:
  the `.php` file is executed directly
  * these `.php` files will create a kernel so that it is bootstrapped, but will
    not actually run the kernel
* otherwise, a new standard web kernel is created and run: this kernel knows how
  to further route requests to their handler


## Variants

All kernels have the following responsibilities, as seen in
`\Alaress\Application\Kernel::__construct`:
* initialize connections to databases (`initialiseExternalConnections`)
  * (may not be required anymore, because a connection was created in
    [instance configuration](../../../configuration/instance-configuration.md))
* register error, exception and shutdown handling
* set up dependency injection
* set up dependencies which are only configurable after dependency injection
  is complete
  * currently sets up sessions, routing table and modules: may not be required
    as all this could be done in DI above
* set up debugging (standard web request only)

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
