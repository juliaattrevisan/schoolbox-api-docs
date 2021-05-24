<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\IAssessable;
use InvalidArgumentException;
use Schoolbox\Learning\Assessment\Participant;

/**
 * Common functionality of rules that deal with assessments
 */
abstract class AbstractAssessableRule implements IAuthorizationRule
{
    /**
     * @param array $params
     * @return IAssessable
     * @throws InvalidArgumentException If unable to find an IAssessable instance in route params
     */
    protected function findAssessable(array $params): IAssessable
    {
        if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
            return $params['assessment'];
        }
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            // TODO deprecated in 18.0. Remove for 18.1.
            trigger_error("'participant' route arg is deprecated", E_USER_DEPRECATED);
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getAssessable();
        }
        throw new InvalidArgumentException('Unable to locate an IAssessable instance in route params');
    }
}
