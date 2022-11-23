# Web Kernel (Standard)

`\Schoolbox\Application\WebRequest\Kernel` is the standard web request kernel:
it configures all things which a web request may need.

## Additional bootstrapping

- CSRF checking
- Loading of routes and modules from their cache into memory

## Running a request

This kernel's `run` method is able to process an incoming request.

Its responsibilities include:
- parsing the request's path to determine if is recognised as an Aura route
  - if so, loading and executing its module and controller, and outputting its
    response
  - if not, sending the request back to be handled via Sitepage


### Aura

### Sitepage

If Aura's routes didn't match, then the request will be sent to be handled via
Sitepage.

A file which matches the request's path will be included
