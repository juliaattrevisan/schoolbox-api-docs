<?php

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

// Run router with requested path
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = $di->get('router')->match($path, $_SERVER);
if ($route instanceof Aura\Router\Route) {
    // do something with the route.
    // For now, I just assume the DI container knows how to get a controller
    // named 'controller:$route->params[controller]', and this controller has a
    // public method named $route->params[action].
    /** @var \Alaress\Web\Controller\Base $controller */
    $controller = $di->get('controller:'.$route->params['controller']);
    /** @var \Alaress\Session\Session $session */
    $session = $di->get('session');

    /** @var \Aura\Web\Response $response */
    $response = null;
    // Has session expired? Assume no user here then, but that should probably
    // be handled in authenticator.
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

        /** @var \Alaress\Authorizer\Authorizer $authorizer */
        $authorizer = $di->get('authorizer');
        $authorizer->authorizePathForUser($route->name, $route->params, $user);

        // If authorizePathForUser() threw no exception, authorization passed!

        // Call the controller method.
        // Ensure that the parameters match up with the names used.
        $method = new ReflectionMethod($controller, $route->params['action']);
        $args   = array();
        foreach ($method->getParameters() as $parameter) {
            $name   = $parameter->getName();
            $args[] = (array_key_exists($name, $route->params)) ? $route->params[$name] : null;
        }
        $method->invokeArgs($controller, $args);
        $response = $controller->getResponse();
    }
    catch (\Alaress\Authorizer\Failure\Unauthorized $e) {
        // Really basic unauthorized response - redirect to a login page.
        $response = $di->get('webFactory')->newResponse();
        $response->redirect->to('/login/?page='.urlencode($path));
    }
    catch (\Alaress\Authorizer\Failure\Forbidden $e) {
        // TODO replace the call to Sitepage::error() method
        error($e->getCode(), $e->getMessage(), 403);
    }
    catch (\Alaress\Authorizer\Failure\Base $e) {
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
        $format = preg_replace('/^\\./', '', $route->params['format']);
        if (empty($format)) {
            $format = 'html';
        }
    }

    // Create a different output type (Sitepage, JSON, whatever) based on a
    // route param, defaulting to HTML
    /** @var \Alaress\Web\Output\Base $output */
    $output = $di->get("webOutput:$format");
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

// we shall assume that if a specific page is requested, the user just be sent to that page regardless of device
if((isset($_GET['page']) && $_GET['page']) && $_GET['page'] != '/') {
    $pageName = $_GET['page'];
}
else {
    // hey, are you a mobile? You should go check out the mobile site. As in, now. Scram.
    if(Cfg::get('mobile_enabled')) {
        $agent = new HttpUserAgent();
        if($agent->isMobile()) {
            SitePage::localRedir('/m/');
        }
    }
    $pageName = 'dashboard';
}

$page = new SitePage();

$page->loadContent($pageName);

$page->outputPage();
