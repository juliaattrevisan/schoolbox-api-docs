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
use Alaress\Web\Request\IsJson;
use Alaress\Web\Request\IsXhr;
use Alaress\Web\Response\Factory;
use Alaress\Web\Response\Outputter;
use Aura\Di\Container;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use RuntimeException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Application\Login\Controller as LoginController;
use Schoolbox\Application\WebKernelAbstract;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use Sentry\Tracing\TransactionSource;
use Throwable;
use Schoolbox\Url\Uri;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
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
     * @throws \DebugBar\DebugBarException
     * @throws \ReflectionException
     */
    public function run()
    {
        // First get the request object.
        /** @var ServerRequestInterface $request */
        $request = $this->di->get('httpRequest');

        // Run router with requested path (trim trailing /)
        $server  = $request->getServerParams();
        $path    = RequestUriParser::parseUrlPath($server);
        $request = $request->withUri($request->getUri()->withPath($path));

        /** @var RouterContainer $router */
        $router  = $this->di->get('router');
        $matcher = $router->getMatcher();
        if ('OPTIONS' === $server['REQUEST_METHOD']) {
            /**
             * if system is requesting OPTIONS lets give them what the endpoint actually accepts, not just all.
             */
            $methods = ['OPTIONS'];
            foreach (["GET", "HEAD", "POST", "PUT", "DELETE", "PATCH"] as $testMethod) {
                $testRequest = $request->withMethod($testMethod);
                if ($matcher->match($testRequest)) {
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
        $route = $matcher->match($request);

        try {
            [$authHeaderUser, $bypassIpTracking] = $this->extractUserFromAuthHeader($server['HTTP_AUTHORIZATION'] ?? null);
            if ($route instanceof Route) {
                $this->outputAuraContent($route, $authHeaderUser, $bypassIpTracking);
            } else {
                $this->outputSitepageContent($path, $authHeaderUser, $bypassIpTracking);
            }
        } catch (Unauthenticated $e) {
            // Unauthenticated response - redirect to a login page.
            // Preserve GET params that aren't "page" or "samlAuth", so the user
            // can get redirected back to what they requested when they log in.
            $query       = new Uri($request->getUri());
            $queryParams = array_diff_key($query->getQueryAsArray(), ['page' => true, 'samlAuth' => true]);

            // using http_build_query to handle array in filters
            $uri = (new Uri($path))->appendQueryParameters($queryParams);

            // Start constructing a response
            $code = 401;

            // Request was JSON: just let outputError build an ApiProblem response
            if ((new IsJson())->isSatisfiedBy($request)) {
                $this->outputError($e, $e->getMessage(), '', [], $code);
                return;
            }

            /** @var Factory $responseFactory */
            $responseFactory = $this->di->get('httpResponseFactory');
            if ((new IsXhr())->isSatisfiedBy($request)) {
                // Fabricate an AJAX response. Don't redirect
                // TODO replace this with standard outputError (ApiProblem style payload)
                // In fact, get rid of IsXhr completely: modern HTTP clients don't set X-Requested-With
                $psr7Response = $responseFactory
                    ->buildJson(['error' => 'Unauthenticated'])
                    ->withStatus($code);
            } else {
                /** @var \Alaress\Session\Session $session */
                $session = $this->di->get('session');
                $session
                    ->getSegment(LoginController::class)
                    ->setFlash(LoginController::LOGIN_ERROR_FLASH, get_class($e));
                $psr7Response = $responseFactory->buildRedirect(
                    $router->getGenerator()->generate('login.getLogin') . '?page=' . urlencode($uri)
                );
            }
            (new Outputter())->output($psr7Response);
        } catch (\Schoolbox\Mapper\Exception\NotFound | Deleted $e) {
            // Mapper exception: something was either deleted or never existed.
            // Output something plain
            $code = $e instanceof Deleted ? 410 : 404;
            if ((new IsJson())->isSatisfiedBy($request)) {
                $this->outputError($e, $e->getMessage(), '', [], $code);
                return;
            }

            /** @var Factory $responseFactory */
            $responseFactory = $this->di->get('httpResponseFactory');
            if ((new IsXhr())->isSatisfiedBy($request)) {
                // Fabricate an AJAX response. Don't redirect
                // TODO replace this with standard outputError (ApiProblem style payload)
                (new Outputter())->output(
                    $responseFactory
                        ->buildJson(['error' => $e->getMessage()])
                        ->withStatus($code)
                );
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
            $this->outputError($e, $e->getMessage(), $e->getInformation(), [], $e->getHttpStatusCode());
        } catch (InvalidPath $e) {
            $this->outputError($e, $e->getMessage(), '', [], 400);
        }
    }


    /**
     * Renders Aura-based content.
     *
     * @param Route     $route            the route to render:
     *                                    the controller and action are
     *                                    extracted from its name.
     * @param User|null $authHeaderUser   User from the `Authorization` header
     *                                    (if provided and valid).
     * @param bool      $bypassIpTracking If true: bypass IP tracking of the
     *                                    session.
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \DebugBar\DebugBarException
     * @throws \ReflectionException
     * @throws Throwable
     */
    private function outputAuraContent(Route $route, ?User $authHeaderUser, bool $bypassIpTracking): void
    {
        // Track this thing
        $this->sentryTransactionManager->startTransaction(
            $route->name,
            null,
            null,
            null,
            TransactionSource::route(),
            $_SERVER['REQUEST_TIME_FLOAT'],
            [
                'url'    => $_SERVER['REQUEST_URI'],
                'method' => $_SERVER['REQUEST_METHOD'],
            ],
        );


        // The route name's format:
        // "[controllerName].[action](:[label])"
        // The : and label are optional.
        preg_match('/^(?P<controller>\w+)\\.(?P<action>\w+)/', $route->name, $parsedName);

        $controllerName = $parsedName['controller'];
        $action         = $parsedName['action'];

        // Get module out of its own cache, instead of from DI
        $module = Registry::buildModuleFromRegistration($this->di, $controllerName);
        $module->initControllerDependencies();

        // Was a user received via an auth header?
        // If so: auth the user for only this request.
        // Currently, this destroys all previous session data: but we presume
        // you aren't making requests with a JWT when you are already logged in.
        $session = null;
        if ($authHeaderUser) {
            $this->authenticateUserForThisRequestOnly($authHeaderUser, $bypassIpTracking);
            $user = $authHeaderUser;
        } else {
            /** @var \Alaress\Session\Session $session */
            $session = $this->di->get('session');

            // Invalid session ID or IP address mismatch:
            // unauthenticate, thus sending back to the login page.
            // If there's a remember, this will kick in on the login page, thus
            // sending the user back to the dashboard.
            if (!$session->isSessionIdValid()) {
                $session->destroy();
                throw new Unauthenticated();
            }

            if (!$session->isSessionIpAddressValid()) {
                $session->destroy();
                throw new Unauthenticated();
            }

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
        $params = $route->attributes;
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

        // Process the response, using a blank one if there is none.
        $psr7Response = $controller->getResponse();
        if ($psr7Response) {
            $redirect = $psr7Response->getHeader('location');
            $isJson   = str_contains($psr7Response->getHeaderLine('content-type'), 'application/json');
        } else {
            $psr7Response = $controller->getResponseFactory()->createResponse();
            $redirect     = false;
            $isJson       = false;
        }

        // Post-processing: if this is a HTML request and not a redirect,
        // store this request in history.

        /* Push the current url into the session history stack
         * pushHistory() is a more recent version of saveHistory() which exists in sitepage
         * aura-based redirecting issues can be tracked back here
         *
         * xhr requests should not be in the history
         * redirects should not be in the history
         * controllers instantiated with "$allowBackToTheFuture = false" should not be in the history
         * json output should not be in the history
         */
        if (
            $session && !($controller->isXhrRequest()
                || $redirect
                || !$controller->getAllowBackToTheFuture()
                || $isJson
            )
        ) {
            $session->pushHistory($_SERVER['REQUEST_URI']);
        }

        // Output whatever kind of response we have
        /** @var Outputter $outputter */
        $outputter = $this->di->get('httpResponseOutputter');
        $outputter->output($psr7Response);

        // Output post-output callbacks
        $postOutputCallbacks = $controller->getPostOutputCallbacks();
        if (count($postOutputCallbacks)) {
            // Earlier implementations used to buffer output so that
            // "Content-Length" could be set.
            // I don't think this is necessary; at least I've seen no difference
            // if it's enabled or not.
            //            header('Content-Encoding: none');
            //            header("Content-Length: " . strlen($output));

            // Finish the request
            fastcgi_finish_request();

            // Allow the process however long it needs to finish:
            // 30 minutes should be more than enough.
            set_time_limit(60 * 30);

            // Callbacks
            foreach ($postOutputCallbacks as $callback) {
                if (is_callable($callback)) {
                    $callback();
                }
            }

            // Close session once done
            // (actions in post-output may require session data)
            session_write_close();
        }
    }


    /**
     * Renders Sitepage-based content.
     *
     * @param string|null $path             the base path that was requested
     * @param User|null   $authHeaderUser   User from the `Authorization` header
     *                                      (if provided and valid).
     * @param bool        $bypassIpTracking If true: bypass IP tracking of the
     *                                      session.
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    private function outputSitepageContent(?string $path, ?User $authHeaderUser, bool $bypassIpTracking): void
    {
        // we shall assume that if a specific page is requested,
        // the user just be sent to that page regardless of device
        if ($path !== null && $path && $path !== '/') {
            $pageName = $path;
        } else {
            $pageName = 'dashboard';
        }

        $this->sentryTransactionManager->startTransaction(
            $pageName,
            null,
            null,
            null,
            TransactionSource::url(),
            $_SERVER['REQUEST_TIME_FLOAT'],
            [
                'url'    => $_SERVER['REQUEST_URI'],
                'method' => $_SERVER['REQUEST_METHOD'],
            ],
        );

        // Was a user received via an auth header?
        // If so: auth the user for only this request.
        // Currently, this destroys all previous session data: but we presume
        // you aren't making requests with a JWT when you are already logged in.
        if ($authHeaderUser) {
            $this->authenticateUserForThisRequestOnly($authHeaderUser, $bypassIpTracking);
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
    public static function installDependencies(Container $di): Container
    {
        parent::installDependencies($di);
        return $di;
    }

    /**
     * @inheritDoc
     * @throws \Throwable
     */
    protected function postInstallDependencies(bool $bypassMaintenanceModeCheck = false): void
    {
        parent::postInstallDependencies($bypassMaintenanceModeCheck); // Check for maintenance mode is done in here.

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
                    '/login/oidc',
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
}
