<?php
/**
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Preview;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        /* Default index page
         */
        $router->addGet('preview.index', $basePath);

        /* Render html fragment, or redirect
         */
        $router->addGet('preview.preview', $basePath . '/{encodedUrl}')
            ->addValues([
                'format' => '.nochrome'
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $matcher->add(
            '/^preview\.(index|preview)/',
            function($params) {
                return array(
                    'params' => $params
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/Templates', 'preview');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:preview',
            $injector->lazyNew('Schoolbox\\Preview\\Controller')
        );
    }
}
