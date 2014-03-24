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
            $factory = new Alaress\Web\Router\RouterFactory();
            return $factory->newInstance();
        }
    )
);

// Session
$di->set(
    'session',
    $di->lazy(
        function() use($di) {
            $factory = new Alaress\Session\Factory($_COOKIE);
            $session = $factory->build();
            $session
                ->setConfig($di->get('config'))
                ->setRequestTime($_SERVER['REQUEST_TIME'])
            ;
            return $session;
        }
    )
);

// Authorization
$di->set('authorizer', $di->lazyNew('Alaress\\Authorizer\\Authorizer'));

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
    'setSession' => $di->lazyGet('session'),
    'setRouter' => $di->lazyGet('router'),
);

// inject web params (yep, even Sitepage!)
$di->params['SitePage']['login'] = false;
$di->set('sitePage', $di->lazyNew('SitePage'));

// Web output class - currently uses Sitepage
$di->setters['Alaress\\Web\\Output\\Html']['setTemplateEngine'] = $di->lazyGet('templateEngine');
$di->setters['Alaress\\Web\\Output\\ThemedHtml']['setSitepage'] = $di->lazyGet('sitePage');
$di->set('webOutput:html', $di->lazyNew('Alaress\\Web\\Output\\ThemedHtml'));
$di->set('webOutput:json', $di->lazyNew('Alaress\\Web\\Output\\Json'));
$di->set('webOutput:file', $di->lazyNew('Alaress\\Web\\Output\\File'));

// Template engine and functions
$di->setters['Alaress\\Twig\\Method\\RouteLookup']['setRouter'] = $di->lazyGet('router');
$di->set('templateEngine:routeLookup', $di->lazyNew('Alaress\\Twig\\Method\\RouteLookup'));

$di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setRouter']     = $di->lazyGet('router');
$di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setAuthorizer'] = $di->lazyGet('authorizer');
$di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setSession']    = $di->lazyGet('session');
$di->set('templateEngine:isRouteAuthorized', $di->lazyNew('Alaress\\Twig\\Method\\IsRouteAuthorized'));

$di->set(
    'templateEngine',
    $di->lazy(
        function() use($di) {
            $config = $di->get('config');
            $twig   = new Twig_Environment(
                $di->get('templateEngine:loader'),
                array('cache' => $config->get('stream_dir').'/templates/', 'debug' => true,)
            );
            $twig->addExtension(new Twig_Extension_Debug());

            // Add the route lookup method
            $routeLookup = new Twig_SimpleFunction(
                'routeLookup',
                array($di->get('templateEngine:routeLookup'), 'execute')
            );
            $twig->addFunction($routeLookup);

            return $twig;
        }
    )
);
$di->set('templateEngine:loader', $di->lazyNew('Twig_Loader_Chain'));
