<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use InvalidArgumentException;

class AssessmentNotDeleted implements IAuthorizationRule
{

    /**
     * Ensures the assessment is not soft-deleted
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     * @throws InvalidPath If the assessment is soft-deleted
     * @throws InvalidArgumentException  If an IAssessable was unable to be found in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessable = $this->findAssessable($params);
        if ($assessable->isDeleted()) {
            throw new InvalidPath("Assessment (id: {$assessable->getId()}) is deleted");
        }
    }


    /**
     * @param array $params
     * @return IAssessable
     * @throws InvalidArgumentException If an IAssessable was unable to be found in route params
     */
    private function findAssessable(array $params): IAssessable
    {
        if ($params['assessment'] instanceof IAssessable) {
            return $params['assessment'];
        }
        if ($params['participant'] instanceof Participant) {
            return $params['participant']->getAssessable();
        }
        throw new InvalidArgumentException('Unable to locate an assessable instance in route params');
        //TO DO: Log to sentry
    }
}
