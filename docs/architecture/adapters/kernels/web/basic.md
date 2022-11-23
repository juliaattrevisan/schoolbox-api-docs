# Web Kernel (Basic)

This web request is designed for when there is no user interface to display.

It is the same as the standard web request kernel, except its dependency
injection container does not set up UI dependencies (e.g. Twig).

> This may become obsolete: the original reasoning for the basic web request
> kernel (performance) is less relevant now that the routing table and module
> configuration are cached.
