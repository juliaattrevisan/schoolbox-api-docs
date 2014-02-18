<?php

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

// Initialise routes
foreach (glob(SITE_root.'/conf/routes/*.php') as $routePath) {
    require $routePath;
}

// Run router with requested path
$route = $di->get('router')->match(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    $_SERVER
);
if ($route instanceof Aura\Router\Route) {
    // do something with the route.
    // For now, I just assume the DI container knows how to get a controller
    // named 'controller:$route->params[controller]', and this controller has a
    // public method named $route->params[action].
    /** @var \Alaress\Web\Controller\Base $controller */
    $controller = $di->get('controller:'.$route->params['controller']);
    call_user_func(array($controller, $route->params['action']), $route->params);

    // Figure out output type.
    // In order of preference, output type is:
    // * set in the response content
    // * set in route
    // * default to 'html'
    /** @var \Aura\Web\Response $response */
    $response = $controller->getResponse();
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
