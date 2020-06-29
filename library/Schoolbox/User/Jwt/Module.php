<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Jwt;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;
use User\Logger;

class Module extends BaseModule
{
    protected $basename = 'userJwt';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        // Routes:
        // getJwt, getJwtForUser
        $isLoggedIn = new Rule\LazyCallback(function () {
            return new Rule\IsLoggedIn();
        });
        $isUserOrSuperUser = new Rule\LazyCallback(function () {
            return new Rule\Any(
                $this->getAuthRule('isSuperuser'),
                new Rule\IsUser('userId')
            );
        });
        $preloadUser = function (array $params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '/', 'getJwtForSelf', $isLoggedIn);
        $this->register('GET', '/{userId}', 'getJwtForUser', $isUserOrSuperUser, $preloadUser);
    }


    /**
     * @param array $params
     * @return array
     */
    private function preloadUser(array $params): array
    {
        if (isset($params['userId'])) {
            try {
                $user = $this->getMapperFactory()->get(UserMapper::class)->findById($params['userId']);
            } catch (Exception $e) {
                throw new NotFound();
            }

            return ['user' => $user];
        }

        throw new \InvalidArgumentException('A user ID must be provided in path param "userId"');
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, [
            'userAccessMapper'    => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
            'externalHandler'     => $injector->lazyGet('externalUser'),
            'assumeUserLogMapper' => $this->getMapperFactory()->getLazy(Logger\Mapper::class),
        ]));
    }

}
