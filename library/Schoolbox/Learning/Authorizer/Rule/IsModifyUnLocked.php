<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Authorisation rule to check whether an assessment is locked from modification
 *
 * Does NOT short-circuit for SuperUsers - the module should combine Rules to achieve that effect if desired.
 */
class IsModifyUnLocked extends IsUnLocked
{

    /**
     * @inheritdoc
     */
    protected function isLocked(IAssessable $assessment)
    {
        return $this->getLockedChecker()->isLockedForModify($assessment);
    }
}
