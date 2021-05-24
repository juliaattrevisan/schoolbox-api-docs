<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Fixtures\Season;
use Schoolbox\Fixtures\Division;
use Schoolbox\Fixtures\Team;
use Schoolbox\User;

/**
 * Ensures the season is not archived
 */
class IsSeasonNotArchived implements IAuthorizationRule
{

    /**
     * @param array     $params
     * @param User|null $user
     * @throws Unauthorized If the team's division's season is archived
     * @throws \InvalidArgumentException If unable to locate a Team model in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        if ($this->findSeason($params)->isArchived()) {
            throw new Unauthorized();
        }
    }


    /**
     * Finds a season in route params.
     *
     * It is possible that params may include a season, a team, a division, or
     * some combination thereof. Preference is given to:
     * * season
     * * division
     * * team
     *
     * @param array $params
     * @return Season\Model
     * @throws \InvalidArgumentException If unable to locate a Team model in route params
     */
    private function findSeason(array $params)
    {
        if (isset($params['season']) && $params['season'] instanceof Season\Model) {
            return $params['season'];
        }
        if (isset($params['entity']) && $params['entity'] instanceof Season\Model) {
            return $params['entity'];
        }
        if (isset($params['division']) && $params['division'] instanceof Division\Model) {
            /** @var Division\Model $division */
            $division = $params['division'];
            return $division->getSeason();
        }
        if (isset($params['entity']) && $params['entity'] instanceof Division\Model) {
            /** @var Division\Model $division */
            $division = $params['entity'];
            return $division->getSeason();
        }
        if (isset($params['context']) && $params['context'] instanceof Division\Model) {
            /** @var Division\Model $division */
            $division = $params['context'];
            return $division->getSeason();
        }
        if (isset($params['team']) && $params['team'] instanceof Team\Model) {
            /** @var Team\Model $team */
            $team = $params['team'];
            return $team->getDivision()->getSeason();
        }
        if (isset($params['entity']) && $params['entity'] instanceof Team\Model) {
            /** @var Team\Model $team */
            $team = $params['entity'];
            return $team->getDivision()->getSeason();
        }
        throw new \InvalidArgumentException(
            'Unable to determine if Season is archived. No Season, Division or Team objects found in route params'
        );
    }
}
