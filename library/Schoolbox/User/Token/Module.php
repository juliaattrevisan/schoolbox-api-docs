<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Token;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

/**
 * Module for requests that work with user tokens.
 */
class Module extends BaseModule
{
    protected $basename = 'userToken';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        // Routes:
        // getTokenForSelf, getTokenForUser
        $isLoggedIn        = $this->getAuthRule('isLoggedIn');
        $isUserOrSuperUser = new Rule\LazyCallback(function () {
            return new Rule\All(
                $this->getAuthRule('isLoggedIn'),
                new Rule\Any(
                    $this->getAuthRule('isSuperuser'),
                    new Rule\IsUser('userId')
                )
            );
        });
        $preloadUser = function (array $params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '', 'getTokenForSelf', $isLoggedIn);
        $this->register('POST', '/postDeleteToken', 'postDeleteToken', $isUserOrSuperUser);
        $this->register('POST', '/{userId:\d+}', 'getTokenForUser', $isUserOrSuperUser, $preloadUser);
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
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('user.tokenBuilder'),
            $this->getMapperFactory()->get(Mapper::class),
        );
    }
}
