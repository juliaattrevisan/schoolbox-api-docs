<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Mail;
use \Schoolbox\UserMapper;
use \Schoolbox\User;
use Twig_Loader_Chain;

/**
 * Module for password resets
 *
 * @package Schoolbox\User\Password\Reset
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'userPasswordReset';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'displayCreateRequestForm', $basePath, '');
        $this->addPostRoute($router, 'createRequest', $basePath, '/createRequest');
        $this->addGetRoute($router, 'displayResetForm', $basePath, '/{token}')->addTokens(['token' => '[0-9a-f]+']);
        $this->addPostRoute($router, 'reset', $basePath, '/reset');
    }

    /**
     * Route preloads - currently, loads token into the reset form display
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(displayResetForm|reset)',
            function ($params) {
                return ['reset' => Mapper::getInstance()->findById($params['token']),];
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'userMapper'  => UserMapper::getInstance(),
                    'resetMapper' => Mapper::getInstance(),
                    'mailFactory' => $injector->lazyNew(
                        Mail\TwigEmailFactory::class,
                        [
                            'context' => $injector->lazyNew(
                                Mail\CoreContext::class,
                                ['conf' => $injector->lazyGet('config')]
                            )
                        ]
                    ),
                ]
            )
        );
    }
}
