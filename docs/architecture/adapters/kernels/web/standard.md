# Web Kernel (Standard)

`\Schoolbox\Application\WebRequest\Kernel` is the standard web request kernel:
it configures all things which a web request may need.

## Additional bootstrapping

### CSRF checking

In order to protect against cross-site request forgery attempts: this kernel is
able to reject mutative (e.g. non-`GET`) requests if it determines that the
request's source host doesn't match the instance's host name.

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

Modules will be defined in Schoolbox's
[module configuration](../../../configuration/modules.md).


## Running a request

This kernel's `run` method is able to process an incoming request.

Its responsibilities include:
- parsing the request's path to determine if is recognised as an Aura route
  - if so, loading and executing its module and controller, and outputting its
    response
  - if not, sending the request back to be handled via Sitepage

Alternately: the kernel may have been created without being run. (This happens
if the kernel was created via the
[Requested path exists](README.md#requested-path-exists) mechanism.)

In this case: the steps below are bypassed completely, as `run` is never called.
Instead: as with the Basic kernel's
[Running a request](basic.md#running-a-request) method, any code which is
defined after the kernel is created is run automatically as a
[Sitepage controller](../../controllers/sitepage.md).


### Aura

If the request's path matches an Aura route loaded in the
[Loading of routes and modules](#loading-of-routes-and-modules) step, then the
request will be handled via the Aura system.

> The Aura system is named after the Aura libraries upon which it is built.
> This system was intended to replace the older Sitepage-based system, but has
> not done so completely at time of writing.
>
> Aura itself is showing its age (its Web component does not support the
> [PSR-7](https://www.php-fig.org/psr/psr-7/) standard for HTTP messages), but
> the system Schoolbox has built upon it has proven its fitness for purpose.
>
> See https://auraphp.com/ for more information about the various libraries.

`\Schoolbox\Application\WebRequest\Kernel::outputAuraContent` will perform the
following actions required to output the results of an Aura-handled request:

#### Fetch a module

The matched route's name will contain a controller and action name:
- the controller name will match the name of a module which has been loaded in
  the module cache
- the action name will match a public method of that controller's class

> e.g. if the route's name is "userModule.modify", the controller name will be
> "userModule", and the action name will be "modify".

This module will know how to build the controller which handles the request, and
will also load the preloads and permission checks which are relevant to each of
its actions.


#### User authentication

The user authentication step will either:
- restore the user from the current session, if there is one
- if an `Authorization` request header was provided: attempt to authenticate a
  user based on that


#### Preload objects

The Aura router may parse a path into a set of parameters: e.g. the path
`/adminv2/user/1/modify` may parse into a parameter set `{userId: 1}`.

Most controller actions and permission checks require an actual object, and not
just a object ID, as a method parameter: the preloader is designed to add the
actual object to the parameter set.

In the example above: the `userId` will be passed into a mapper, which will
search for a user whose ID is `1`: this user will be injected into the parameter
set, thus resulting in the set `{userId: 1, user: <User object>}`


#### Check permissions

The module may perform permission checks, in order to verify that the user
authenticated in [User authentication](#user-authentication) is actually
authorized to perform the action that they have requested.

The parameters from the route, as well as from
[Preloading objects](#preload-objects), may be passed into the module's
permission check.


#### Run the controller action

At this point, the controller is then built from the module, and the requested
action is run.

The controller method matching the name of the action is introspected for its
parameters, and those parameters are matched against the route and preloader
params: then, the controller method is called with those parameters.

Controllers are documented further in
[their own section](../../controllers/aura.md).


#### Render the controller's output

The controller method should have injected a
`\Alaress\Web\Output\OutputInterface` instance into the controller's response
object.

This object's `output` method will then be called to generate actual output.

Various variants of the output object exist, depending on the type of content
to be output:
- `\Alaress\Web\Controller\Base::buildHtmlOutput` can construct HTML output,
  with the Schoolbox instance's headers, sidebars and footers
- `\Alaress\Web\Controller\Base::buildJsonOutput` can construct JSON output


### Sitepage

If Aura's routes didn't match the request's path, then the request will be sent
to be handled via Sitepage.

> Sitepage is the controller paradigm which existed prior to Aura.
>
> It is considered deprecated: no new code should be written upon it (with the
> _possible_ exception of [Basic](basic.md) web requests).

`\Schoolbox\Application\WebRequest\Kernel::outputSitepageContent` sends requests
to Sitepage. This method will include a file in `www/` which matches the
request's path directly (minus the `.php` extension), and its code will be
executed as a [Sitepage controller](../../controllers/sitepage.md).
