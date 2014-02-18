<?php

use Aura\Di;

// Do dependency injection stuff
// How do I get this container elsewhere without using a global?
/** @global $di */
$di = new Di\Container(new Di\Factory());

// Configuration
$di->set('config', $di->lazyNew('Schoolbox\\ConfigLoader'));

// Web router
$di->set(
    'router',
    $di->lazy(
        function() {
            $factory = new Aura\Router\RouterFactory();
            return $factory->newInstance();
        }
    )
);

// Set up a web factory and its request/responses
$di->params['Aura\\Web\\WebFactory'] = array('globals' => $GLOBALS);
$di->set('webFactory', $di->lazyNew('Aura\\Web\\WebFactory'));

// inject request and response into controller
// (I may need to mangle requests in pre-processing though)
$di->setters['Alaress\\Web\\Controller\\Base'] = array(
    'setRequest' => $di->lazy(
        function() use($di) {
            return $di->get('webFactory')->newRequest();
        }
    ),
    'setResponse' => $di->lazy(
        function() use($di) {
            return $di->get('webFactory')->newResponse();
        }
    ),
);

// inject web params (yep, even Sitepage!)
//$di->params['SitePage']['login'] = false;
$di->set('sitePage', $di->lazyNew('SitePage'));

// Web output class - currently uses Sitepage
$di->setters['Alaress\\Web\\Output\\ThemedHtml']['setTemplateEngine'] = $di->lazyGet('templateEngine');
$di->setters['Alaress\\Web\\Output\\ThemedHtml']['setSitepage']       = $di->lazyGet('sitePage');
$di->set('webOutput:html', $di->lazyNew('Alaress\\Web\\Output\\ThemedHtml'));
$di->set('webOutput:json', $di->lazyNew('Alaress\\Web\\Output\\Json'));

$di->set(
    'templateEngine',
    $di->lazy(
        function() use($di) {
            $config = $di->get('config');
            $root   = $config->get('siteRoot');
            $skin   = $config->get('site_location');

            $loader = new Twig_Loader_Chain();

            $customLoader = new Alaress\Twig\Loader\Custom(array("$root/templates/$skin"));
            $customLoader->setConfigLoader($config);
            $loader->addLoader($customLoader);
            $loader->addLoader(new Twig_Loader_Filesystem(array("/", $root)));

            $twig   = new Twig_Environment($loader);
            $twig->addExtension(new Twig_Extension_Debug());
            return $twig;
        }
    )
);
