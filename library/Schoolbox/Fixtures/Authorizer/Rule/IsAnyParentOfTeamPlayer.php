<?php

/**
 * IsAnyParentOfTeamPlayer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use External_UserInterface;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Fixtures\Team\Metadata\Factory;

class IsAnyParentOfTeamPlayer implements IAuthorizationRule
{
    /**
     * The array key in $params that contains the user ID.
     *
     * @var string
     */
    protected $paramsKey = 'id';

    /**
     * The external user interface
     *
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * IsAnyParentOfUsers constructor.
     *
     * @param External_UserInterface $externalDb
     */
    public function __construct(External_UserInterface $externalDb)
    {
        $this->setExternalDb($externalDb);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        /* Players in the Team specified
         */
        $factory = new Factory($params['context']);
        $teamMetadata = $factory->produce();

        $allPlayers = $teamMetadata->getPlayers()->getOffset($params['teamId']);

        $playerIds = [];

        /** @var User $currentPlayer */
        foreach ($allPlayers as $currentPlayer) {
            if ($currentPlayer['user'] instanceof User) {
                $playerIds[] = $currentPlayer['user']->getExternalId();
            }
        }

        /* The children belonging to the Parent currently logged in
         */
        $externalChildren = $this->getExternalDb()->getEnrolledStudents($user->getExternalId()) ? : [];
        $externalChildIds = [];

        foreach ($externalChildren as $externalChild) {
            $externalChildIds[] = $externalChild['RelatedID'];
        }

        /* Players who are in the Team currently being viewed
         * and also in the Parent's external children list
         */
        $children = array_intersect($playerIds, $externalChildIds);

        if (count($children)) {
            return true;
        }

        throw new Unauthorized();
    }

    /* Getters/Setters
     */

    /**
     * @return External_UserInterface
     */
    private function getExternalDb()
    {
        return $this->externalDb;
    }

    /**
     * @param External_UserInterface $externalDb
     */
    private function setExternalDb($externalDb)
    {
        $this->externalDb = $externalDb;
    }
}
