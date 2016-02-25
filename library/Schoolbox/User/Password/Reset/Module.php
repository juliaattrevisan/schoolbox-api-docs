<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
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
        $this->addGetRoute($router, 'passwordReset', $basePath, '');
        $this->addPostRoute($router, 'requestPasswordReset', $basePath, '/requestPasswordReset');
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
