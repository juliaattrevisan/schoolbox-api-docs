<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Can the current session user preview the assessment, thus becoming a participant?
 * Requires:
 * - session user is an instructor
 * - session user has not been explicitly excluded
 * - assessment has a dynamic plan available to insert user into
 */
class CanPreview extends AbstractParticipationRule
{

    /**
     * Performs an authorization check.
     *
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessable    = $this->findAssessable($params);
        $student       = $this->findStudent($params);
        $participation = $assessable->getParticipantIfExtant($student);

        // Is the user explicitly excluded?
        if ($participation && $participation->isExcluded()) {
            throw new Unauthorized("User (id: {$student->getId()} has been explicitly excluded from assessment (id: {$assessable->getId()})");
        }

        // No participation, and no dynamic plan to insert a participation into?
        if (!$participation && !$assessable->getDynamicPlan()) {
            throw new Unauthorized("User (id: {$student->getId()} has no participation but assessment (id: {$assessable->getId()}) lacks a dynamic plan");
        }

        return true;
    }
}
