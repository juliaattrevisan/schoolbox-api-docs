<?php
/**
 * @author      Matthew Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Authorisation rule to check whether an assessment is locked from modification
 *
 * Does NOT short-circuit for SuperUsers - the module should combine Rules to achieve that effect if desired.
 */
class IsModificationUnLocked extends IsUnLocked
{

    /**
     * @inheritdoc
     */
    protected function isLocked(IAssessable $assessment)
    {
        return $this->getLockedChecker()->isLockedForModification($assessment);
    }
}
