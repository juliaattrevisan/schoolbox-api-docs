<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\WebRequest;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Failure\Unauthenticated;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Csrf;
use Alaress\DebugBar\DataCollector\RouteCollector;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\Module\Registry;
use Alaress\Web\Output\Json;
use Alaress\Web\Output\OutputInterface;
use Alaress\Web\Request\IsJson;
use Alaress\Web\Request\IsXhr;
use Aura\Di\Container;
use Aura\Router\Route;
use Aura\Web\Request;
use Aura\Web\Response;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use ReflectionMethod;
use RuntimeException;
use Schoolbox\API\Token\Jwt;
use Schoolbox\API\Token\JwtParser;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Application\Login\Controller as LoginController;
use Schoolbox\Application\WebKernelAbstract;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\MapperFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Throwable;
use Laminas\Uri\Uri;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Alaress\DebugBar\DataCollector\TidewaysCollector;
use Database;
use Psr\Log\NullLogger;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;

/**
 * Designed for full web requests, including rendering a full UI
 */
class Kernel extends WebKernelAbstract
{
    /** @var bool */
    protected $debugMode;


    /**
     * @throws RuntimeException
     * @throws SchoolboxException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\External\Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->runMiddleware();
    }


    /**
    * @param bool $bool
    * @throws RuntimeException
    */
    public function setDebugMode($bool)
    {
     // only allow once
        if (is_null($this->debugMode)) {
            $this->debugMode = !!$bool;
        } else {
            throw new RuntimeException("Debug mode has already been set, cannot set again");
        }
    }


    /**
    * @return bool
    */
    public function inDebugMode()
    {
        return $this->debugMode;
    }


    /**
     * Execute the main script
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @throws \DebugBar\DebugBarException
     * @throws \ReflectionException
     * @throws \Laminas\Uri\Exception\InvalidArgumentException
     */
    public function run()
    {
        // Run router with requested path (trim trailing /)
        $path  = RequestUriParser::parseUrlPath($_SERVER);

        // take a copy
        $server = $_SERVER;

        // check for a method override and act accordingly
        if (!empty($server['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $server['REQUEST_METHOD'] = strtoupper($server['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }

        if ('OPTIONS' === $server['REQUEST_METHOD']) {
            /**
             * if system is requesting OPTIONS lets give them what the endpoint actually accepts, not just all.
             */
            $methods = ['OPTIONS'];
            foreach (["GET", "HEAD", "POST", "PUT", "DELETE", "PATCH"] as $testMethod) {
                if (false !== $this->di->get('router')->match($path, ['REQUEST_METHOD' => $testMethod])) {
                    $methods[] = $testMethod;
                }
            }
            http_response_code(204);
            //return the list of available HTTP methods, only works for AURA routes
            header(sprintf('Allow: %s', implode(', ', $methods)));
            //tell CORS what headers are specifically allowed, so api.schoolbox.com.au actually works
            header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization");
            //initial allow all HTTP METHODS again cause CORS gets sad if we don't do this...
            header("Access-Control-Allow-Methods: *");
            exit;
        }
        $route = $this->di->get('router')->match($path, $server);

        try {
            if ($route instanceof Route) {
                $this->outputAuraContent($route, $server);
            } else {
                $this->outputSitepageContent($path, $server);
            }
        } catch (Unauthenticated $e) {
            // Unauthenticated response - redirect to a login page.
            // Preserve GET params that aren't "page" or "samlAuth", so the user
            // can get redirected back to what they requested when they log in.
            /** @var Request $request */
            $request  = $this->di->get('webFactory')->newRequest();
            $uri      = new Uri($path);
            $query    = [];
            foreach ($request->query as $key => $value) {
                if (!in_array($key, ['page', 'samlAuth'])) {
                    $query[$key] = $value;
                }
            }
            $uri->setQuery($query);

            // Start constructing a response
            /** @var Response $response */
            $response = $this->di->get('webFactory')->newResponse();
            $code     = 401;

            // Request was JSON: just let outputError build an ApiProblem response
            if ((new IsJson())->isSatisfiedBy($request)) {
                $this->outputError($e, $e->getMessage(), '', [], $code);
                return;
            }

            if ((new IsXhr())->isSatisfiedBy($request)) {
                // Fabricate an AJAX response. Don't redirect
                // TODO replace this with standard outputError (ApiProblem style payload)
                // In fact, get rid of IsXhr completely: modern HTTP clients don't set X-Requested-With
                $response->status->setCode($code);
                $response->content->set([
                    'format' => 'json',
                    'data'   => ['error' => 'Unauthenticated'],
                ]);
                $output = $this->di->get("webOutput:json");
            } else {
                /** @var \Alaress\Session\Session $session */
                $session = $this->di->get('session');
                $session
                    ->getSegment(LoginController::class)
                    ->setFlash(LoginController::LOGIN_ERROR_FLASH, get_class($e));
                $output = $this->di->get("webOutput:noContent");
                $response->redirect->to($this->di->get('router')->generate('login.getLogin') . '?page=' . urlencode($uri));
            }
            $output->output($response);
        } catch (\Schoolbox\Mapper\Exception\NotFound | Deleted $e) {
            // Mapper exception: something was either deleted or never existed.
            // Output something plain
            /** @var Request $request */
            $request  = $this->di->get('webFactory')->newRequest();
            /** @var Response $response */
            $response = $this->di->get('webFactory')->newResponse();
            $code     = $e instanceof Deleted ? 410 : 404;
            if ((new IsJson())->isSatisfiedBy($request)) {
                $this->outputError($e, $e->getMessage(), '', [], $code);
                return;
            }

            if ((new IsXhr())->isSatisfiedBy($request)) {
                // Fabricate an AJAX response. Don't redirect
                // TODO replace this with standard outputError (ApiProblem style payload)
                $response->status->setCode($code);
                $response->content->set([
                    'format' => 'json',
                    'data'   => ['error' => $e->getMessage()],
                ]);
                $output = $this->di->get("webOutput:json");
                $output->output($response);
            } else {
                $this->outputError($e, $e->getMessage(), '', [], $code);
            }
        } catch (Forbidden $e) {
            // Not allowed
            $errorMessage = ErrorMapper::getInstance()->getMessageById($e->getCode());
            $this->outputError($e, $errorMessage, '', [], 403);
        } catch (NotFound $e) {
            // "That which you are looking for cannot be found"
            $errorMessage = ErrorMapper::getInstance()->getMessageById($e->getCode());
            $this->outputError($e, $errorMessage, '', [], 404);
        } catch (Unauthorized $e) {
            // This seems to be the most appropriate error code for unauthorized calls.
            // TODO set headers on $response instead of calling header()
            header('WWW-Authenticate: FormBased');
            $errorMessage = ErrorMapper::getInstance()->getMessageById($e->getCode());
            $this->outputError($e, $errorMessage, '', [], 401);
        } catch (SchoolboxException $e) {
            /* Generic Schoolbox Exception spawned by ErrorMapper,
             * thrown by Controllers. ErrorMapper already did the
             * db call to retrieve the content, so error() doesn't
             * need to do it itself. It just renders the content.
             */
            $this->outputError($e, $e->getMessage(), '', [], $e->getHttpStatusCode());
        } catch (InvalidPath $e) {
            $this->outputError($e, $e->getMessage(), '', [], 400);
        }
    }


    /**
     * Renders Aura-based content.
     *
     * @param Route $route  the route to render: the controller and action are
     *                      extracted from its name.
     * @param array $server Vars from $_SERVER
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @throws \DebugBar\DebugBarException
     * @throws \ReflectionException
     * @throws \Laminas\Uri\Exception\InvalidArgumentException
     * @throws Throwable
     */
    private function outputAuraContent(Route $route, array $server): void
    {
        // The route name's format:
        // "[controllerName].[action](:[label])"
        // The : and label are optional.
        preg_match('/^(?P<controller>\w+)\\.(?P<action>\w+)/', $route->name, $parsedName);

        $controllerName = $parsedName['controller'];
        $action         = $parsedName['action'];

        // Get module out of its own cache, instead of from DI
        $module = Registry::buildModuleFromRegistration($this->di, $controllerName);
        $module->initControllerDependencies();

        // User is from:
        // * Authorization header
        // * Session, if not in auth header
        $user = $this->extractUserFromAuthHeader($server['HTTP_AUTHORIZATION'] ?? null);

        // Did token auth fail to find a user?
        $session = null;
        if ($user) {
            $this->authenticateUserForThisRequestOnly($user);
        } else {
            /** @var \Alaress\Session\Session $session */
            $session = $this->di->get('session');

            // Do authorization here
            // TODO Inject user getter into authorizer, and use it to determine:
            // * if user is there
            // * if user session is timed out or not
            /** @var \Schoolbox\User $user */
            $user = $session->getAuthUser();
            if ($session->isAuthSessionAlive()) {
                $session->updateLastTime();
            }
        }

        // The preloader may throw an exception.
        // It should be ignored if the user is not authorized,
        // because we always want a login screen.
        /** @var \Alaress\Web\Router\PathMatcher $preloader */
        $preloader          = $this->di->get('preloader');
        $preloaderException = null;

        // Augment route params with preloader params, if possible
        $params = $route->params;
        try {
            $params = $preloader->check($route->name, $params);
        } catch (\Exception $preloaderException) {
            // Store the preloader exception for later
        }

        // now the preloading is done initialise the controller
        $controller = $module->installControllerDependencies(
            $module->buildController(),
            $this->di,
        );
        unset($module);

//        // now the preloading is done initialise the controller
//        $module->initControllers($this->di);
//        unset($module);
//
//        // TODO replace with a create controller method
//        /** @var \Alaress\Web\Controller\Base $controller */
//        $controller = $this->di->get('controller:' . $controllerName);

        // The user is now injected directly into the controller, instead of the
        // user being loaded from the session within the controller.
        $controller->setUser($user);

        // Authorize, throwing whatever exceptions might be thrown.
        /** @var \Alaress\Authorizer\Authorizer $auth */
        $auth = $this->di->get('authorizer');

        try {
            $auth->authorizePathForUser($route->name, $params, $user);
        } catch (Unauthenticated $ue) {
            // Unauthenticated:
            // always throw this, even if there was preloader exception
            throw $ue;
        } catch (Throwable $ae) {
            // Some other exception (including unauthorized):
            // throw the preloader exception in preference to this
            if ($preloaderException instanceof Throwable) {
                throw $preloaderException;
            }
            throw $ae;
        }

        // No auth exception:
        // throw the preloader exception if there was one
        if ($preloaderException instanceof Throwable) {
            throw $preloaderException;
        }

        // If authorizePathForUser() threw no exception, authorization passed!
        // Call the controller method.
        // Ensure that the parameters match up with the names used.
        $method = new ReflectionMethod($controller, $action);
        $args   = [];
        foreach ($method->getParameters() as $parameter) {
            $name   = $parameter->getName();
            $args[] = (array_key_exists($name, $params)) ? $params[$name] : null;
        }

        // Set the route into the debug bar
        if (DebugBar::isEnabled()) {
            DebugBar::getInstance()->addCollector(
                new RouteCollector(sprintf('%s::%s', $method->class, $method->name))
            );
        }

        $method->invokeArgs($controller, $args);
        $response = $controller->getResponse();
        $output   = $response->content->get();
        if (!$output instanceof OutputInterface) {
            if (is_array($output)) {
                // Figure out output type.
                // In order of preference, output type is:
                // * set in the response content
                // * set in route
                // * default to 'html'
                $format = $output['format'] ?? null;
                if (empty($format)) {
                    // Strip leading . from format
                    $format = preg_replace('/^\\./', '', $params['format'] ?? '');
                    if (empty($format)) {
                        $format = 'html';
                    }
                }
                // Create a different output type (Sitepage, JSON, whatever) based on a
                // route param, defaulting to HTML
                $output = $this->di->get("webOutput:$format");
            } elseif (null === $output) {
                $output = $this->di->get("webOutput:noContent");
            } else {
                // Maybe it has a __toString method, or it IS actually a string.
                // Ram it into the base output type.
                $output = $this->di->get("webOutput:base");
            }
        }

        // Post-processing: if this is a HTML request and not a redirect,
        // store this request in history.
        $isXhr                = $controller->getRequest()->isXhr();
        $redirect             = $controller->getResponse()->headers->get('location');
        $allowBackToTheFuture = $controller->getAllowBackToTheFuture();
        $isJson               = $output instanceof Json;

        /* Push the current url into the session history stack
         * pushHistory() is a more recent version of saveHistory() which exists in sitepage
         * aura-based redirecting issues can be tracked back here
         *
         * xhr requests should not be in the history
         * redirects should not be in the history
         * controllers instantiated with "$allowBackToTheFuture = false" should not be in the history
         * json output should not be in the history
         *
         * Note that storm will tell you $isJson is always false; this is a bit misleading.
         * It's because storm can't statically interpret:
         *
         * $output = $this->di->get("webOutput:$format");
         *
         * ...to potentially overwrite $output into a new var somewhat pseudo-polymorphically
         */
        if ($session && !($isXhr || $redirect || !$allowBackToTheFuture || $isJson)) {
            $session->pushHistory($_SERVER['REQUEST_URI']);
        }

        $output->output($response);
    }


    /**
     * Renders Sitepage-based content.
     *
     * @param string|null $path  the base path that was requested
     * @param array       $server Vars from $_SERVER
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    private function outputSitepageContent(?string $path, array $server): void
    {
        // we shall assume that if a specific page is requested,
        // the user just be sent to that page regardless of device
        if ($path !== null && $path && $path !== '/') {
            $pageName = $path;
        } else {
            $pageName = 'dashboard';
        }

        // Was an auth header received?
        // If so: auth the user for only this request.
        // Currently, this destroys all previous session data: but we presume
        // you aren't making requests with a JWT when you are already logged in.
        $user = $this->extractUserFromAuthHeader($server['HTTP_AUTHORIZATION'] ?? null);
        if ($user) {
            $this->authenticateUserForThisRequestOnly($user);
        }

        // Time to start the session
        // TODO is this necessary?
        /** @var \Alaress\Session\Session $session */
        $session = $this->di->get('session');
        if (!$session->isStarted()) {
            $session->start();
        }

        /** @var \SitePage $sitePage */
        $sitePage = $this->di->get('sitePage');

        // If at this point, we unfortunately must init Sitepage's session.
        // This is because all existing requests which passed through here used
        // the SitePage variant which initialized the session: if we don't init
        // now, it won't ever be initialized.
        $sitePage->initSession();
        $sitePage->loadContent($pageName);
        $sitePage->outputPage();
    }


    /**
     * Initialise debugging
     *
     * @return void
     * @throws \DebugBar\DebugBarException
     * @throws \Schoolbox\External\Exception
     */
    protected function initialiseDebugging(): void
    {
        // Set up the debug bar, and add listeners for various parts of the debug bar
        // (database calls, XHProf)
        // Should go in a DI container instead of a static class.
        if (DebugBar::isEnabled()) {
            DebugBar::init();

            $debugBar = DebugBar::getInstance();

            // Throw away existing connection and reconnect with persistence off
            $database = Database::getInstance();
            $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true,]);

            $collector = $database->getCollector();
            if (!$debugBar->hasCollector($collector->getName())) {
                $debugBar->addCollector($collector);
            }

            // Install Tideways into the debug bar
            /** @var IConfigLoader $config */
            $config = $this->getDependencyInjector()->get('config');
            if ($config->get('debug_xhprof') && extension_loaded('tideways_xhprof')) {
                $tidewaysCollector = new TidewaysCollector();
                if (!$debugBar->hasCollector($tidewaysCollector->getName())) {
                    $debugBar->addCollector(new TidewaysCollector());
                }
            }
        }
    }


    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * @param Container $di
     * @return Container
     * @throws RuntimeException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     */
    protected function installDependencies(Container $di): Container
    {
        parent::installDependencies($di);
        return $di;
    }

    /**
     * @inheritDoc
     * @throws \Throwable
     */
    protected function postInstallDependencies(): void
    {
        parent::postInstallDependencies();

        $this->initRoutes();
        $this->initModules();
    }


    /**
     * Web requests require CSRF protection: that belongs in middleware.
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws SchoolboxException
     */
    protected function runMiddleware(): void
    {
        // Ghetto CSRF middleware. Reject request if:
        // * It's not a GET or HEAD request
        // ** Disregard X-Http-Method-Override, as that can be set by client
        // * It's not a deliberately allowed request, API endpoint or vhost
        // ** In general: any request that authenticates via token instead of
        //    via session should be allowed
        // * Its headers don't match
        // Accept request if it somehow doesn't have a REQUEST_METHOD at all;
        // AFAIK this isn't fakeable, and this gets called by some CLI scripts
        // despite it being the WebRequest bootstrap. (Go figure.)

        $di             = $this->getDependencyInjector();

        /** @var IConfigLoader $config */
        $config         = $di->get('config');

        $fqdn           = trim($config->get('site_hostname') . '.' . $config->get('site_domain'), ';.');
        $csrfCheck      = new Csrf\HeaderCheck($_SERVER, $fqdn);
        $csrfMiddleware = new Csrf\Middleware($csrfCheck);
        if (
            !$csrfMiddleware->verify(
                $_SERVER,
                ['*/dav/index.php'],
                [
                    '/saml/consume.php*',
                    '/api/*',
                    '/loginForm.php*',
                    '/liss/liss.php',
                    '/lti-consumer/*',
                    '/lti-prototype/*',
                    '/lti-platform/*',
                ]
            )
        ) {
            // Let's just say 403 Forbidden for now
            // TODO replace the call to Sitepage::error() method
            /** @var \SitePage $page */
            $page = $di->get('sitePage');
            $page->error(ErrorMapper::ERROR_FORM_SUBMIT, 'Your request has been blocked as a proactive security measure. Please review your browser plugins and disable any that you do not need, and try again; if you continue to receive this message, please report the issue to ' . $config->get('feedback_text') . '.', 403);
        }
    }

    /**
     * Extract a user from the "Authorization" HTTP header, if that header
     * exists.
     *
     * @param string|null $authHeader
     * @return User|null
     * @throws Unauthenticated if auth header exists but is invalid
     * @throws Unauthenticated if auth header is valid, but doesn't point to a
     *                         valid user
     */
    private function extractUserFromAuthHeader(?string $authHeader): ?User
    {
        if (isset($authHeader) && preg_match('/^Bearer\\s+(.*)$/i', $authHeader, $matches)) {
            [, $token] = $matches;

            /** @var JwtParser\IParser $tokenParser */
            $tokenParser = $this->get('user.jwtParser');

            if ($tokenParser->accepts($token)) {
                return $tokenParser->determineUser($token);
            }
        }
        return null;
    }

    /**
     * Authenticate the user for this request only, storing their data in the
     * session.
     *
     * After this request finishes: restore the original session if somebody was
     * logged in, or destroy the session completely if nobody was logged in.
     *
     * @param User $user
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    private function authenticateUserForThisRequestOnly(User $user): void
    {
        // Determine who was logged in, and preserve their data
        /** @var \Alaress\Session\Session $sessionDataStore */
        $sessionDataStore   = $this->di->get('session');
        $currentSessionData = null;
        $currentUser        = $sessionDataStore->getAuthUser();
        if ($currentUser->getId()) {
            $currentSessionData = $_SESSION;
        }

        // Now authenticate the given user
        $sessionHandler = new \Session();
        $sessionHandler->create($user->getUsername());

        // Also: override the DI user with this user?
        // Should not be required because the DI user is fetched from the
        // session, anyway.
        // (This would die when the container was locked, anyway)
//        $this->di->set('user', $user);

        // Restore or destroy the session when all is done
        $this->getShutdownHandler()->registerFunction(
            function () use ($sessionHandler, $currentSessionData) {
                if (isset($currentSessionData)) {
                    $_SESSION = $currentSessionData;
                } else {
                    $sessionHandler->destroy();
                }
            }
        );
    }
}
