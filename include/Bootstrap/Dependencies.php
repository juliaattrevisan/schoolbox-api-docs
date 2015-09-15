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
            $factory = new Alaress\Session\Factory(
                $_COOKIE,
                $di->get('config'),
                $_SERVER['REQUEST_TIME']
            );
            $session = $factory->build();
            return $session;
        }
    )
);

// Preload of route parameters
$di->set('preloader', $di->lazyNew('Alaress\\Web\\Router\\PathMatcher'));

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
    'setAuthorizer' => $di->lazyGet('authorizer'),
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
// Template loader: sets up link to templates without a namespace.
$di->set(
    'templateEngine:loader',
    $di->lazy(
        function() use($di) {
            $config      = $di->get('config');
            $chainLoader = new Twig_Loader_Chain();
            $baseLoader  = new Twig_Loader_Filesystem(array());
            // Paths: prefer templates/{site_location} over templates/core
            $basePath     = $config->get('siteRoot').'/templates/';
            $overridePath = $basePath.$config->get('site_location');
            $corePath     = $basePath.'core';
            if (is_dir($overridePath)) {
                $baseLoader->addPath($overridePath);
            }
            $baseLoader->addPath($corePath);
            // Core path: always uses templates/core, even if templates/{site_location} exists
            // Used as a base for default path templates to inherit from.
            $baseLoader->addPath($corePath, 'core');
            $chainLoader->addLoader($baseLoader);
            return $chainLoader;
        }
    )
);

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
                array(
                    'cache' => $config->get('stream_dir').'/templates/',
                    'debug' => $config->get('debug_templates'),
                )
            );
            $twig->addExtension(new Twig_Extension_Debug());
            $twig->addExtension(new Twig_Extensions_Extension_Text());

            // HTML Purifier escape strategy
            // Please use the filter below instead.
            $twig->getExtension('core')->setEscaper('purify', function($twig, $string) {
                return Scrub::html($string);
            });

            // HTML Purifier filter - known to output safe HTML
            $twig->addFilter(
                new Twig_SimpleFilter(
                    'purify',
                    array('Scrub', 'html'),
                    array('is_safe' => array('html'))
                )
            );

            // HTML2Text filter
            $twig->addFilter(
                new Twig_SimpleFilter(
                    'html2text',
                    array('Scrub', 'html2text')
                )
            );

            // Bytes to string filter
            $twig->addFilter(
                new Twig_SimpleFilter(
                    'bytes',
                    array('Alaress_Byte_Format', 'bytesToString')
                )
            );

            // Global access to config object and a date locale object
            $twig->addGlobal('config', $config);
            $twig->addGlobal('locale', array(
                'date' => new Alaress_Date_Locale_Australian()
            ));

            // Add the route lookup and authorization methods
            $twig->addFunction(
                new Twig_SimpleFunction(
                    'routeLookup',
                    array($di->get('templateEngine:routeLookup'), 'execute')
                )
            );
            $twig->addFunction(
                new Twig_SimpleFunction(
                    'isRouteAuthorized',
                    array($di->get('templateEngine:isRouteAuthorized'), 'execute')
                )
            );

            return $twig;
        }
    )
);
