<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
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
     * @var Access
     */
    private $access;


    /**
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->access = $access;
    }


    /**
     * Performs an authorization check.
     *
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!$user) {
            throw new InvalidArgumentException("No session user available to test permissions");
        }

        $student       = $this->findStudent($params);
        $assessable    = $this->findAssessable($params);

        // Session user can only preview themselves
        if ($student->getId() != $user->getId()) {
            throw new Unauthorized("You may only preview yourself. Student (id: {$student->getId()}) != User (id: {$user->getId()})");
        }

        // Session user must be an instructor
        if ($this->access->getFolderAccess($user, $assessable->getBaseContext()->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
            throw new Unauthorized("Only instructors can preview an assessment.");
        }

        // Is the user explicitly excluded?
        $participation = $assessable->getParticipantIfExtant($student);
        if (!$participation) {
            throw new Unauthorized("User (id: {$student->getId()} is deleted or has been explicitly excluded from assessment (id: {$assessable->getId()})");
        }

        // No participation, and no dynamic plan to insert a participation into?
        if (!$participation && !$assessable->getDynamicPlan()) {
            throw new Unauthorized("User (id: {$student->getId()} has no participation but assessment (id: {$assessable->getId()}) lacks a dynamic plan");
        }

        return true;
    }
}
