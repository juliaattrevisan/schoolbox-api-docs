<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Mapper;
use ICollection;

/**
 * Responsible for moving Assessments between Contexts
 * @todo SA-1343
 */
class Transplantor
{
    /**
     * Move the given assessments from their current context to a new context
     *
     * Should support moving between any context, even from UnitImpl to UnitSpec
     *
     * @param ICollection|IAssessable[] $assessments the assessments to move
     * @param AssessmentContext         $destination the location into which to move the assessments
     * @throws Mapper\Exception                      if any part of the move fails
     * @return ICollection|IAssessable[]             the NEW assessments, as moved
     */
    public function moveToNewContext(ICollection $assessments, AssessmentContext $destination)
    {
        // TODO - SA-1343 implement me
        throw new \BadMethodCallException('Not yet implemented.');
    }
}
