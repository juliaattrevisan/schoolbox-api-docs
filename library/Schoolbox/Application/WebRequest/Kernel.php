<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\WebRequest;

use Alaress\Authorizer\Failure\Base;
use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Failure\Unauthenticated;
use Alaress\Authorizer\Failure\Unauthorized;
use Aura\Di;
use Aura\Router\Route;
use ReflectionMethod;
use RuntimeException;
use Zend\Uri\Uri;

class Kernel extends \Alaress\Application\Kernel
{

    /** @var bool */
    protected $debugMode;


    /**
    * Initialise
    */
    public function __construct()
    {
        $this->boot = new Bootstrap($this);
        $this->boot();
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
    */
    public function run()
    {
        // Run router with requested path (trim trailing /)
        $path  = preg_replace('|/$|', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        $route = $this->di->get('router')->match($path, $_SERVER);
        if ($route instanceof Route) {
            // The route name's format:
            // "[controllerName].[action](:[label])"
            // The : and label are optional.
            preg_match('/^(?P<controller>\w+)\\.(?P<action>\w+)/', $route->name, $parsedName);
            $controllerName = $parsedName['controller'];
            $action         = $parsedName['action'];
            /** @var \Alaress\Web\Controller\Base $controller */
            $controller = $this->di->get('controller:'.$controllerName);
            /** @var \Alaress\Session\Session $session */
            $session = $this->di->get('session');
            /** @var \Aura\Web\Response $response */
            $response = null;
            // Has session expired? Assume no user here then, but that should probably
            // be handled in authenticator.
            $params = array();
            try {
                // Do authorization here
                // TODO Inject user getter into authorizer, and use it to determine:
                // * if user is there
                // * if user session is timed out or not
                /** @var \Schoolbox\User $user */
                $user = $session->getAuthUser();
                if ($session->isAuthSessionAlive()) {
                    $session->updateLastTime();
                }

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

                // Authorize, throwing whatever exceptions might be thrown.
                /** @var \Alaress\Authorizer\Authorizer $authorizer */
                $auth = $this->di->get('authorizer');
                try {
                    $auth->authorizePathForUser($route->name, $params, $user);
                } catch (Unauthenticated $ue) {
                    // Unauthenticated:
                    // always throw this, even if there was preloader exception
                    throw $ue;
                } catch (\Exception $ae) {
                    // Some other exception (including unauthorized):
                    // throw the preloader exception in preference to this
                    if ($preloaderException instanceof \Exception) {
                        throw $preloaderException;
                    }
                    throw $ae;
                }

                // No auth exception:
                // throw the preloader exception if there was one
                if ($preloaderException instanceof \Exception) {
                    throw $preloaderException;
                }
                // If authorizePathForUser() threw no exception, authorization passed!
                // Call the controller method.
                // Ensure that the parameters match up with the names used.
                $method = new ReflectionMethod($controller, $action);
                $args   = array();
                foreach ($method->getParameters() as $parameter) {
                    $name   = $parameter->getName();
                    $args[] = (array_key_exists($name, $params)) ? $params[$name] : null;
                }
                $method->invokeArgs($controller, $args);
                $response = $controller->getResponse();
            } catch (Unauthenticated $e) {
                // Really basic unauthenticated response - redirect to a login page.
                // Preserve GET params that aren't "page" (or "samlAuth"?)
                $uri   = new Uri($path);
                $query = array();
                foreach ($controller->getRequest()->query as $key => $value) {
                    if (!in_array($key, array('page', 'samlAuth'))) {
                        $query[$key] = $value;
                    }
                }
                $uri->setQuery($query);
                $response = $this->di->get('webFactory')->newResponse();
                $response->redirect->to('/login/?page='.urlencode($uri));
            }
            catch (Forbidden $e) {
                // TODO replace the call to Sitepage::error() method
                error($e->getCode(), $e->getMessage(), 403);
            }
            catch (Unauthorized $e) {
                // This seems to be the most appropriate error code for unauthorized calls.
                // TODO set headers on $response instead of calling header()
                header('WWW-Authenticate: FormBased');
                error($e->getCode(), $e->getMessage(), 401);
            }
            catch (Base $e) {
                error($e->getCode(), $e->getMessage(), 500);
            }
           // Figure out output type.
           // In order of preference, output type is:
           // * set in the response content
           // * set in route
           // * default to 'html'
            /** @var \Aura\Web\Response $response */
            $content  = $response->content->get();
            $format   = $content['format'];
            if (empty($format)) {
                // Strip leading . from format
                $format = preg_replace('/^\\./', '', $params['format']);
                if (empty($format)) {
                    $format = 'html';
                }
            }
            // Create a different output type (Sitepage, JSON, whatever) based on a
            // route param, defaulting to HTML
            /** @var \Alaress\Web\Output\Base $output */
            $output = $this->di->get("webOutput:$format");
            $output->setResponse($response);
            $output->output();
            // Post-processing: if this is a HTML request and not a redirect,
            // store this request in history.
            $isXhr    = $controller->getRequest()->isXhr();
            $redirect = $controller->getResponse()->headers->get('location');
            if (!($isXhr || $redirect)) {
                $session->pushHistory($_SERVER['REQUEST_URI']);
            }
            exit;
        }

        $page = $this->di->get('sitePage');
        $page->loadContent($_GET['page']);
        $page->outputPage();
    }
}
