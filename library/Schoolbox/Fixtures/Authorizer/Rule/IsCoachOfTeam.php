<?php

/**
 * IsCoachOfTeam
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Fixtures\Coach\Model as Coach;
use Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use Schoolbox\Fixtures\Result\Model as Result;
use Schoolbox\Fixtures\Team\Metadata\Factory;

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
         * sportsfixturesResult.getResultForTeam
         *
         * When you're viewing the Create form
         * sportsfixturesResult.getCreateFormForTeam
         */
        if (($params['context'] instanceof Match) && (is_numeric($params['teamId']))) {
            $match  = $params['context'];
            $teamId = $params['teamId'];
        }
        /* When you're viewing the Modify Form
         * sportsfixturesResult.getModifyForm
         *
         * When you're persisting data from the Update form
         * sportsfixturesResult.updateEntity
         */
        else if ($params['entity'] instanceof Result) {

            /** @var Result $result */
            $result = $params['entity'];

            /** @var Match $match */
            $match = MatchMapper::getInstance()->findById($result->getMatch()->getId());
            $teamId = $result->getTeam()->getId();
        } else {
            throw new Unauthorized();
        }

        /* Coaches of the Team specified
         */
        $factory = new Factory($match);
        $teamMetadata = $factory->produce();

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
