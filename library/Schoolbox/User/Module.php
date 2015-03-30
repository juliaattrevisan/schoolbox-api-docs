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
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use \Schoolbox\UserMapper;
use \Schoolbox\User;

class Module extends BaseModule
{
    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $router->addGet('user.impersonate', $basePath . '/switch/{userId}');
        $router->addGet('user.recoverSession', $basePath . '/recoverSession/');


    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $matcher->add(
            '/^user.impersonate$/',
            function ($params) {
                return array(
                    'user' => UserMapper::getInstance()->findById($params['userId']),
                );
            }
        );
    }

    /**
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->addRule('/^user.(impersonate|recoverSession)$/', new Rule\IsLoggedIn());
        $authorizer->addRule('/^user.impersonate$/', new Rule\Callback(
            function ($params, User $user) {
                // Do not allow unlinked users to switch
                foreach($user->getLinkedUser() as $linkedUser ) {
                    if($params['userId'] == $linkedUser->getId()) {
                       return true;
                    }
                }
                throw new Unauthorized();
            }
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:user', $injector->lazyNew('Schoolbox\\User\\Controller'));
    }



}
