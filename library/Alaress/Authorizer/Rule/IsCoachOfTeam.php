<?php
/**
 * IsCoachOfTeam
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Authorizer\Rule;

use External_UserInterface;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Base;
use Alaress\Authorizer\Failure\Unauthorized;
use \Schoolbox\SportsFixtures\Coach\Model as Coach;
use \Schoolbox\SportsFixtures\Team\Metadata;

class IsCoachOfTeam implements IAuthorizationRule
{
    /**
     * The array key in $params that contains the user ID.
     *
     * @var string
     */
    protected $paramsKey = 'id';

    /**
     * IsAnyParentOfUsers constructor.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        /* Coaches of the Team specified
         */
        $teamMetadata = new Metadata($params['context']);

        $allCoaches = $teamMetadata->getCoaches()->getOffset($params['teamId']);

        /** @var User $sessionUser */
        $sessionUser = $_SESSION['user'];

        /** @var Coach $currentCoach */
        foreach ($allCoaches as $currentCoach) {
            if ($currentCoach->getUser()->getId() == $sessionUser->getId()) {
                return true;
            }
        }

        throw new Unauthorized();
    }
}