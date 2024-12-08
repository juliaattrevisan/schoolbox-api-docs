<?php

/**
 * Module definition for userspace actions
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Oidc\Server\RelyingParty\Mapper as RelyingPartyMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use User\Logger;

class Module extends BaseModule
{
    protected $basename = 'user';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        // Impersonate and recover routes
        $loggedInPermission    = new Rule\IsLoggedIn();
        $impersonatePermission = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\Callback(
                function ($params, User $user) {
                    // Do not allow unlinked users to switch
                    foreach ($user->getLinkedUsers() as $linkedUser) {
                        if ($params['userId'] == $linkedUser->getId()) {
                            return true;
                        }
                    }
                    throw new Unauthorized();
                }
            ),
            new Rule\Callback(
                function ($params, User $user) {
                    $userToImpersonateRoleId = $params['user']->getRole()->getId();

                    if (
                        $user->hasServicePermission(
                            UserAccess::SERVICE_IMPERSONATOR,
                            UserAccess::ACCESS_FULL,
                            $userToImpersonateRoleId
                        )
                    ) {
                        throw new Unauthorized();
                    }
                }
            )
        );
        $impersonatePreload = fn ($params) => [
            'user' => $this->getUserMapper()->findById($params['userId']),
        ];
        $this->register(
            self::METHOD_GET,
            '/switch/{userId}',
            'impersonate',
            new Rule\All($loggedInPermission, $impersonatePermission),
            $impersonatePreload,
        );

        // Recover session: alias for recovering previous session
        $this->register(
            self::METHOD_GET,
            '/recoverSession',
            'recoverSession',
            $loggedInPermission,
        );
        $this->register(
            self::METHOD_GET,
            '/recoverSession/previous',
            'recoverSessionPrevious',
            $loggedInPermission,
        );
        $this->register(
            self::METHOD_GET,
            '/recoverSession/initial',
            'recoverSessionInitial',
            $loggedInPermission,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(Logger\Mapper::class),
            $this->getMapperFactory()->get(RelyingPartyMapper::class),
            $this->injector->get('user.logout.requestFactory'),
            $this->injector->get('httpClient'),
        );
    }


    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
