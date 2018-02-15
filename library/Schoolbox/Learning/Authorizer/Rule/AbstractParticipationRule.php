<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use InvalidArgumentException;


/**
 * Common functionality of rules that deal with the intersection of an assessment and student
 */
abstract class AbstractParticipationRule extends AbstractAssessableRule
{
    /**
     * @param array $params
     * @return User
     * @throws InvalidArgumentException If unable to find a User instance in route params
     */
    protected function findStudent(array $params): User
    {
        if (isset($params['student']) && $params['student'] instanceof User) {
            return $params['student'];
        }
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            // TODO deprecated in 18.0. Remove for 18.1.
            trigger_error("'participant' route arg is deprecated", E_USER_DEPRECATED);
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getUser();
        }
        throw new InvalidArgumentException('Unable to locate a User instance in route params');
    }
}
