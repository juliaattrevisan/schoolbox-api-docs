# Modules

Schoolbox's modules are configured in `conf/modules`: these configurations each
load a module's routes at a given base URL.

## Core modules

Core modules exist directly within `conf/modules`: these modules are available
to all instances.

## Custom modules

Custom modules exist within `conf/modules/Custom`: these modules are only
available to an instance whose configured school code matches the sub-folder
name, e.g. `conf/modules/Custom/Help` is available only to instances with the
school code `Help`.
