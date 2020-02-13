<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\WebRequest;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Failure\Unauthenticated;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Csrf;
use Alaress\DebugBar\DataCollector\RouteCollector;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\Web\Output\Json;
use Alaress\Web\Output\OutputInterface;
use Aura\Router\Route;
use ReflectionMethod;
use RuntimeException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Application\WebKernelAbstract;
use Schoolbox\Mapper\Exception\Deleted;
use Zend\Uri\Uri;
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
     * @throws \Aura\Di\Exception\ServiceNotFound
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
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @throws \DebugBar\DebugBarException
     * @throws \ReflectionException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     */
    public function run()
    {
        $parsedUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (!$parsedUrl) {
            // because parse_url returns false when the url contains ":", replacing it.
            $parsedUrl = parse_url(str_replace(':', '%3A', $_SERVER['REQUEST_URI']), PHP_URL_PATH);
            $parsedUrl = str_replace('%3A', ':', $parsedUrl);
        }

        // Run router with requested path (trim trailing /)
        $path  = preg_replace('|/$|', '', $parsedUrl);

        // take a copy
        $server = $_SERVER;

        // check for a method override and act accordingly
        if (!empty($server['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $server['REQUEST_METHOD'] = strtoupper($server['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }


        $route = $this->di->get('router')->match($path, $server);

        if ($route instanceof Route) {
            $this->outputAuraContent($route, $path);
        } else {
            $this->outputSitepageContent($_GET['page'] ?? null);
        }
    }


    /**
     * Renders Aura-based content.
     *
     * @param Route  $route the route to render: the controller and action are
     *                      extracted from its name.
     * @param string $path  the base path that was requested
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @throws \DebugBar\DebugBarException
     * @throws \ReflectionException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Throwable
     */
    private function outputAuraContent(Route $route, string $path): void
    {
        // The route name's format:
        // "[controllerName].[action](:[label])"
        // The : and label are optional.
        preg_match('/^(?P<controller>\w+)\\.(?P<action>\w+)/', $route->name, $parsedName);

        $controllerName = $parsedName['controller'];
        $action         = $parsedName['action'];

        /** @var \Alaress\Module\Module $module */
        $module = $this->di->get('module:' . $controllerName);
        $module->initControllerDependencies();

        try {
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

            /** @var \Aura\Web\Response $response */
            $response = null;

            // Has session expired? Assume no user here then, but that should probably
            // be handled in authenticator.
            $params = [];

            // The preloader may throw an exception.
            // It should be ignored if the user is not authorized,
            // because we always want a login screen.
            /** @var \Alaress\Web\Router\PathMatcher $preloader */
            $preloader          = $this->di->get('preloader');
            $preloaderException = null;

            try {
                $params = $preloader->check($route->name, $route->params);

            } catch (\Exception $preloaderException) {
                // Store the preloader exception for later
            }

            // now the preloading is done initialise the controller
            $module->initControllers($this->di);
            unset($module);

            /** @var \Alaress\Web\Controller\Base $controller */
            $controller = $this->di->get('controller:' . $controllerName);

            // Authorize, throwing whatever exceptions might be thrown.
            /** @var \Alaress\Authorizer\Authorizer $auth */
            $auth = $this->di->get('authorizer');

            try {
                $auth->authorizePathForUser($route->name, $params, $user);

            } catch (Unauthenticated $ue) {
                // Unauthenticated:
                // always throw this, even if there was preloader exception
                throw $ue;

            } catch (\Throwable $ae) {
                // Some other exception (including unauthorized):
                // throw the preloader exception in preference to this
                if ($preloaderException instanceof \Throwable) {
                    throw $preloaderException;
                }
                throw $ae;
            }

            // No auth exception:
            // throw the preloader exception if there was one
            if ($preloaderException instanceof \Throwable) {
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
        } catch (Unauthenticated $e) {
            // Really basic unauthenticated response - redirect to a login page.
            // Preserve GET params that aren't "page" (or "samlAuth"?)
            $uri   = new Uri($path);
            $query = [];
            foreach ($controller->getRequest()->query as $key => $value) {
                if (!in_array($key, ['page', 'samlAuth'])) {
                    $query[$key] = $value;
                }
            }
            $uri->setQuery($query);
            $response = $this->di->get('webFactory')->newResponse();
            if ($controller->getRequest()->isXhr()) {
                // Fabricate an AJAX response. Don't redirect
                $response->status->setCode(401);
                $response->content->set([
                    'format' => 'json',
                    'data'   => ['error' => 'Unauthenticated'],
                ]);
            } else {
                $session->getSegment(\Schoolbox\Application\Login\Controller::class)
                    ->setFlash(\Schoolbox\Application\Login\Controller::LOGIN_ERROR_FLASH, get_class($e));
                $response->redirect->to($this->di->get('router')->generate('login.getLogin').'?page='.urlencode($uri));
            }
        } catch (\Schoolbox\Mapper\Exception\NotFound|Deleted $e) {
            $response = $this->di->get('webFactory')->newResponse();
            $code     = $e instanceof Deleted ? 410 : 404;
            if ($controller->getRequest()->isXhr()) {
                // TODO replace this with standard outputError (ApiProblem style payload)
                // Fabricate an AJAX response. Don't redirect
                $response->status->setCode($code);
                $response->content->set([
                    'format' => 'json',
                    'data'   => ['error' => $e->getMessage()],
                ]);
            } else {
                $this->outputError($e, $e->getMessage(), '', [], $code);
                return;
            }
        } catch (Forbidden $e) {
            $errorMessage = ErrorMapper::getInstance()->getMessageById($e->getCode());
            $this->outputError($e, $errorMessage, '', [], 403);
            return;
        } catch (NotFound $e) {
            // "That which you are looking for cannot be found"
            $errorMessage = ErrorMapper::getInstance()->getMessageById($e->getCode());
            $this->outputError($e, $errorMessage, '', [], 404);
            return;
        } catch (Unauthorized $e) {
            // This seems to be the most appropriate error code for unauthorized calls.
            // TODO set headers on $response instead of calling header()
            header('WWW-Authenticate: FormBased');
            $errorMessage = ErrorMapper::getInstance()->getMessageById($e->getCode());
            $this->outputError($e, $errorMessage, '', [], 401);
            return;
        } /* Generic Schoolbox Exception spawned by ErrorMapper,
             * thrown by Controllers. ErrorMapper already did the
             * db call to retrieve the content, so error() doesn't
             * need to do it itself. It just renders the content.
             */
        catch (SchoolboxException $e) {
            $this->outputError($e, $e->getMessage(), '', [], $e->getHttpStatusCode());
            return;
        }
        /** @var OutputInterface $output */
        $output = $response->content->get();
        if (!$output instanceof OutputInterface) {
            if (is_array($output)) {
                // Figure out output type.
                // In order of preference, output type is:
                // * set in the response content
                // * set in route
                // * default to 'html'
                $format = $output['format'];
                if (empty($format)) {
                    // Strip leading . from format
                    $format = preg_replace('/^\\./', '', $params['format']);
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
        if (!($isXhr || $redirect || !$allowBackToTheFuture || $isJson)) {
            $session->pushHistory($_SERVER['REQUEST_URI']);
        }

        $output->output($response);
    }


    /**
     * Renders Sitepage-based content.
     *
     * @param string|null $path the base path that was requested
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function outputSitepageContent(?string $path): void
    {
        // we shall assume that if a specific page is requested,
        // the user just be sent to that page regardless of device
        if ($path !== null && $path && $path !== '/') {
            $pageName = $path;
        } else {
            $pageName = 'dashboard';
        }

        /** @var \SitePage $sitePage */
        $sitePage = $this->di->get('sitePage');
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
    protected function initialiseDebugging():void
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
            if ($config->get('debug_xhprof') && extension_loaded('tideways')) {
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
     * @return void
     * @throws RuntimeException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     */
    protected function installDependencies(): void
    {
        parent::installDependencies();

        $di = $this->getDependencyInjector();

        // register an empty logger to maintain interfaces as required
        $di->values['logger'] = new NullLogger();

        // Mapper notifications:
        // They rely upon the session being injected into them, so do that.
        $di->setters['Schoolbox\\Mapper']['setSessionData'] = $di->lazy(function() use($di) {
            /** @var \Alaress\Session\Session $session */
            $session = $di->get('session');
            if (!$session->isStarted()) {
                $session->start();
            }
            return isset($_SESSION) ? $_SESSION : array();
        });

        // initialise session
        /** @var \Alaress\Session\Session $session */
        $session = $di->get('session');
        $session->start();

        $this->initModules();
    }


    /**
     * Web requests require CSRF protection: that belongs in middleware.
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
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

        /** @var IConfigLoader $config */
        $di             = $this->getDependencyInjector();
        $config         = $di->get('config');
        $fqdn           = trim($config->get('site_hostname') . '.' . $config->get('site_domain'), ';.');
        $csrfCheck      = new Csrf\HeaderCheck($_SERVER, $fqdn);
        $csrfMiddleware = new Csrf\Middleware($csrfCheck);
        if (!$csrfMiddleware->verify(
            $_SERVER,
            ['*/dav/index.php'],
            [
                '/saml/consume.php*',
                '/api/*',
                '/loginForm.php*',
                '/liss/liss.php',
                '/lti-consumer/*',
            ]
        )) {
            // Let's just say 403 Forbidden for now
            // TODO replace the call to Sitepage::error() method
            /** @var \SitePage $page */
            $page = $di->get('layoutEngine');
            $page->error(ErrorMapper::ERROR_FORM_SUBMIT, 'Invalid Request Origin', 403);
        }


        // Commented out for now: this was largely an experiment
//        // Ghetto token-based authentication.
//        // If it finds a user: create a session for them
//        /** @var MapperFactory $mapperFactory */
//        /** @noinspection PhpUnhandledExceptionInspection */
//        $mapperFactory = $di->get('mapperFactory');
//        $tokenParser   = new TokenParser(
//            $mapperFactory->get(UserMapper::class),
//            (new JwtVerifierBuilder())
//                // I guess this is configurable.
//                // We'll probably have multiple oAuth providers we accept too
//                ->setIssuer($config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER))
//                ->setAdaptor(new FirebasePhpJwt())
//                ->setDiscovery(new Oidc())
//                ->setAudience([$config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID)]);
//                ->build()
//        );
//        $user          = $tokenParser->findUserFromServerVars($_SERVER);
//        if ($user) {
//            // do something with that user
//        }
    }
}
