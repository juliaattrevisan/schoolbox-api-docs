<?php

/**
 * Twig function for checking whether a route is authorized for a user.
 *
 * By default checks the currently logged-in user, but can accept a different
 * user as a parameter.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Twig\Method;

use Alaress\Authorizer\Authorizer;
use Alaress\Session\Session;
use Schoolbox\User;

class IsRouteAuthorized
{
    public function __construct(
        private Authorizer $authorizer,
        private Session $session,
    ) {
    }

    /**
     * Performs a reverse lookup on the route given its name, and checks if that
     * path is accessible to the given user.
     *
     * @param  string   $name    The name of the route to look up.
     * @param  array    $params  Any params to pass as part of the route.
     * @param User|null $user    The user to authorize. If not set, defaults to
     *                           the currently logged in user.
     * @return bool
     */
    public function execute(string $name, array $params = [], ?User $user = null)
    {
        if (!($user instanceof User)) {
            $user = $this->session->getAuthUser();
        }
        return $this->authorizer->isPathAuthorizedForUser($name, $params, $user);
    }
}
