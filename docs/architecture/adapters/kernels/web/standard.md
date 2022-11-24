# Web Kernel (Standard)

`\Schoolbox\Application\WebRequest\Kernel` is the standard web request kernel:
it configures all things which a web request may need.

## Additional bootstrapping

### CSRF checking

In order to protect against cross-site request forgery attempts: this kernel is
able to reject mutative (e.g. non-`GET`) requests if it determines that the
request's source host doesn't match its target host.

This is handled in `\Schoolbox\Application\WebRequest\Kernel::runMiddleware`.

(See https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#verifying-origin-with-standard-headers
for more information.)

### Loading of routes and modules

`\Schoolbox\Application\WebRequest\Kernel::postInstallDependencies` calls
`\Alaress\Application\Kernel::initRoutes` and
`\Alaress\Application\Kernel::initModules` in order to load Aura's route table
and module cache into the kernel.

- Routes are required so that the [Running a request](#running-a-request) step
  may determine whether the request is handled by Aura or Sitepage
- Modules are required so that requests handled by Aura know which code module
  to execute

These caches may be pre-built by a CLI tool `tools/module/refresh-cache.php`, or
on request if the cache has expired.


## Running a request

This kernel's `run` method is able to process an incoming request.

Its responsibilities include:
- parsing the request's path to determine if is recognised as an Aura route
  - if so, loading and executing its module and controller, and outputting its
    response
  - if not, sending the request back to be handled via Sitepage


### Aura

If the request's path matches an Aura route loaded in the
[Loading of routes and modules](#loading-of-routes-and-modules) step, then the
request will be handled via the Aura system.

`\Schoolbox\Application\WebRequest\Kernel::outputAuraContent` will perform the
following actions required to output the results of an Aura-handled request:

#### Fetching a module

The matched route's name will contain a controller and action name:
- the controller name will match the name of a module which has been loaded in
  the module cache
- the action name will match a public method of that controller's class

This module will know how to build the controller which handles the request, and
will also load the preloads and permission checks which are relevant to each of
its actions.


#### User authentication

The user authentication step will either:
- restore the user from the current session, if there is one
- if an `Authorization` request header was provided: attempt to authenticate a
  user based on that


#### Preloading objects

The Aura router may parse a path into a set of parameters: e.g. the path
`/adminv2/user/1/modify` may parse into a parameter set `{userId: 1}`.

Most controller actions and permission checks require an actual object, and not
just a object ID, as a method parameter: the preloader is designed to add the
actual object to the parameter set.

In the example above: the `userId` will be passed into a mapper, which will
search for a user whose ID is `1`: this user will be injected into the parameter
set, thus resulting in the set `{userId: 1, user: <User object>}`


#### Permission checking



that the current user has permission to perform the requested action


- run the controller action
- render the results of that controller action


### Sitepage

If Aura's routes didn't match the request's path, then the request will be sent
to be handled via Sitepage.

`\Schoolbox\Application\WebRequest\Kernel::outputSitepageContent` sends requests
to Sitepage. This method will include a file in `www/` which matches the
request's path directly (minus the `.php` extension), and its code will be
executed.
