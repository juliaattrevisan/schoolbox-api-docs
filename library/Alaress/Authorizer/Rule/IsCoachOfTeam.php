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
     * IsCoachOfTeam constructor.
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
        if ($params['action'] == 'sportsfixturesResult.getResultForTeam') {
            $match = $params['context'];
            $teamId = $params['teamId'];
        }
        /* When you're viewing the Modify Form
         */
        else if ($params['action'] == 'sportsfixturesResult.getModifyForm') {

            /** @var Result $result */
            $result = $params['entity'];

            /** @var Match $match */
            $match = MatchMapper::getInstance()->findById($result->getMatch()->getId());
            $teamId = $result->getTeam()->getId();
        }
        /* When you're persisting data from the Create form, for the first time
         */
        else if ($params['action'] == 'sportsfixturesResult.createResultForTeam') {

            /** @var Match $match */
            $match = MatchMapper::getInstance()->findById($params['matchId']);
            $teamId = $params['teamId'];
        }
        /* Unknown state - die
         */
        else {
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

        /* If you aren't a coach, die
         */
        throw new Unauthorized();
    }
}