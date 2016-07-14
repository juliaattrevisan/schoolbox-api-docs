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
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use \Schoolbox\SportsFixtures\Result\Model as Result;
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
        /* Viewing the Team Match Page
         */
        if ($params['contextId'] && $params['teamId']) {
            $match = $params['context'];
            $teamId = $params['teamId'];
        }
        /* When you're coming from the Modify Form
         */
        else if ($params['entityId']) {

            /** @var Result $result */
            $result = $params['entity'];

            /** @var Match $match */
            $match = MatchMapper::getInstance()->findById($result->getMatch()->getId());
            $teamId = $result->getTeam()->getId();
        }
        else {
            /* TODO: I have no identifying data when I'm creating a new result -_-
             */
            throw new Unauthorized();
        }

        /* Coaches of the Team specified
         */
        $teamMetadata = new Metadata($match);

        $allCoaches = $teamMetadata->getCoaches()->getOffset($teamId);

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