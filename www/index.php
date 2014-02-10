<?php

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

// Initialise routes
foreach (glob(SITE_root.'/conf/routes/*.php') as $route) {
    require $route;
}

// Requested route is still jammed into $_GET[page], so get it out of that
$route = $di->get('router')->match(
    parse_url($_GET['page'], PHP_URL_PATH),
    $_SERVER
);
if ($route) {
    // do something with the route.
    var_dump(parse_url($_GET['page'], PHP_URL_PATH), $route);
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
