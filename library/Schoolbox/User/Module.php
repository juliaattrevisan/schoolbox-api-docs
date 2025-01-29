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
                /*
                 * User-to-impersonate is linked to logged-in-user.
                 */
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
                /*
                 * User-to-impersonate has a role AND
                 * User-to-impersonate's role is impersonateable by logged-in-user AND
                 * User-to-impersonate is not a superuser AND
                 * User-to-impersonate is not a pastoral moderator at any level of access
                 */
                function ($params, User $user) {
                    // In this function, the logged-in user is a non-superuser (because of Rule\Any(Rule\isSuperuser, ..., [thisRule]).

                    $userToImpersonate = $params['user'];

                    if ($userToImpersonate->getRole()->getId() === null) {
                        // If the user to impersonate does not have a role (perhaps due to a hydrating problem), deny.
                        throw new Unauthorized();
                    }

                    if (
                        // If the logged-in user does not have permission to impersonate the role of the user to impersonate, deny.
                        !$user->hasServicePermission(
                            UserAccess::SERVICE_IMPERSONATOR,
                            UserAccess::ACCESS_FULL,
                            $userToImpersonate->getRole()->getId()
                        )
                    ) {
                        throw new Unauthorized();
                    }

                    if ($userToImpersonate->isSuperuser()) {
                        // Superusers may not be impersonated by non-superusers.
                        throw new Unauthorized();
                    }

                    if ($userToImpersonate->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)) {
                        // Pastoral moderators may not be impersonated by non-superusers.
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
