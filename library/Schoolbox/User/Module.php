<?php
/**
 * Module definition for userspace actions
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Mail;
use \Schoolbox\UserMapper;
use \Schoolbox\User;
use \Alaress\Authorizer\Failure\Unauthorized;
use Twig_Loader_Chain;

class Module extends BaseModule
{
    protected $basename = 'user';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'impersonate', $basePath, '/switch/{userId}');
        $this->addGetRoute($router, 'recoverSession', $basePath, '/recoverSession');
        $this->addGetRoute($router, 'passwordReset', $basePath, '/passwordReset');
        $this->addPostRoute($router, 'requestPasswordReset', $basePath, '/requestPasswordReset');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            'impersonate',
            function ($params) {
                return array(
                    'user' => UserMapper::getInstance()->findById($params['userId']),
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '(impersonate|recoverSession)', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                'impersonate',
                new Rule\Callback(
                    function ($params, User $user) {
                        // Do not allow unlinked users to switch
                        foreach ($user->getLinkedUser() as $linkedUser) {
                            if ($params['userId'] == $linkedUser->getId()) {
                                return true;
                            }
                        }
                        throw new Unauthorized();
                    }
                )
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
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'userMapper'  => UserMapper::getInstance(),
                    'resetMapper' => Password\Reset\Mapper::getInstance(),
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
