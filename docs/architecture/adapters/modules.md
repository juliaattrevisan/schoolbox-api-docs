# Modules

Schoolbox's module configuration is responsible for the definition of a module
at a base path.

The module itself defines:
- how to build the controller which may handle its actions
- a set of actions which this module may handle, including:
  - the HTTP methods and paths used to invoke them
  - the controller method which handles the action
  - any preloads of data which relate to variables within the path
  - any authorization checks the current user must pass in order to call the
    controller method


## Definition

All modules extend the base class `\Alaress\Module\Module`.

```php

use Alaress\Authorizer\Rule;
use Schoolbox\UserMapper;

class MyModule extends \Alaress\Module\Module
{
    /**
     * The basename is used internally by Schoolbox's route registrations:
     * it ensures that a route which is registered in this module may be
     * associated back to this module, and also that this module's Twig
     * templates may be included from within other modules.
     *
     * This name must be unique within the Schoolbox application.
     */
    protected $basename = 'myModule';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        // The HTTP method (GET, POST, etc.) which this route exists for.
        $httpMethod = self::METHOD_GET;

        // The path which this route exists for.
        // This will be prefixed with the base path which this module was
        // configured with (see ../configuration/modules.md), and if a HTTP
        // request is received for that path, the controller will be created.
        // Wildcards may be used in the path; e.g. {userId} here will match
        // any value, and be passed in the `userId` key to preloads, permission
        // checks and controller actions.
        $path = '/user/{userId}';

        // If necessary: convert any parameters parsed out of the path into
        // actual objects.
        // These objects may then be used in either permission checks or as
        // parameters to the controller method.
        $preload = function (array $params) {
            if (isset($params['userId'])) {
                $params['user'] = $this->getMapperFactory()
                    ->get(UserMapper::class)
                    ->findById();
            }
            return $params;
        };

        // Any permission checks which the logged-in user must pass in order to
        // request this route.
        // In this case, the user must be a superuser:
        // all other users will be rejected.
        $permissions = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        // The name of the method within the controller which handles requests
        // for this route.
        // The controller itself will be created by `buildController`.
        // This method may accept parameters: if so, those parameters must have
        // been loaded as part of the path or in a preload callback.
        $controllerMethodName = 'getIndex';

        // And finally register the route.
        $this->register(
            $httpMethod,
            $path,
            $controllerMethodName,
            $permissions,
            $preload,
        );
    }

    /**
     * This method must return a controller object.
     */
    public function buildController(): \Alaress\Web\Controller\Base
    {
        return new Controller();
    }

    /**
     * This method defines where this module's Twig templates may be found.
     *
     * Twig template includes for the namespace `$this->basename` will look
     * inside this folder.
     *
     * For example:
     * {% include '@myModule/base.twig' %} will include the template `base.twig`
     * in this folder.
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
```
