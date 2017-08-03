<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Fixtures\Team;
use Schoolbox\User;

/**
 * Ensures the team's division's season is not archived
 */
class IsTeamNotArchived implements IAuthorizationRule
{

    /**
     * @param array     $params
     * @param User|null $user
     * @throws Unauthorized If the team's division's season is archived
     * @throws \InvalidArgumentException If unable to locate a Team model in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        if ($this->findTeam($params)->getDivision()->getSeason()->isArchived()) {
            throw new Unauthorized();
        }
    }


    /**
     * @param array $params
     * @return Team\Model
     * @throws \InvalidArgumentException If unable to locate a Team model in route params
     */
    private function findTeam(array $params)
    {
        if (isset($params['team']) && $params['team'] instanceof Team\Model) {
            return $params['team'];
        }
        if (isset($params['entity']) && $params['entity'] instanceof Team\Model) {
            return $params['entity'];
        }
        throw new \InvalidArgumentException(
            "Unable to determine if Team's division is archived. No Team instance found in route params"
        );
    }
}
