<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Class CourseManagedFields
 *
 * An abstraction for Course Managed Fields and some of the related behaviours
 * Course Managed Fields currently applies only to Due Work
 *
 * Course Managed fields are made readonly so that teachers can't randomly redefine
 * an assessment that is supposed to be standardised for all who attempt it.
 * These are set at a course level so any imports are subjected to the setting.
 *
 * Course Managed fields, when readonly, never have their values persisted in the backend
 * so even if you subvert the ui, your changes are disregarded.
 *
 * @package Schoolbox\Learning\Assessment
 */
class CourseManagedFields
{

    /**
     * @var User
     */
    private User $viewer;


    /**
     *
     * @var IAssessable
     */
    private IAssessable $assessment;


    /**
     * CourseManagedFields constructor.
     *
     * @param User          $viewer
     * @param IAssessable $assessment
     */
    public function __construct(User $viewer, IAssessable $assessment)
    {
        $this->viewer     = $viewer;
        $this->assessment = $assessment;
    }


    /**
     * We can use the presence of the Course Managed checkbox in course contexts so
     * users get an idea of why their fields are suddenly readonly. Whether you style
     * it up as enabled or disabled will tell users about the state of their assessment.
     * You could alternatively use this logic to present it as a meta, rather than as
     * a disabled ui element.
     *
     * The Course Managed checkbox is visible if:
     *
     *    - The assessment is either in a Specification or Implementation context.
     *      Any other context means the checkbox should not be presented on the ui,
     *      because you're creating an assessment outside a Course.
     *
     *    - Folders are considered an Implementation context, but this is a special case
     *      and the checkbox shouldn't be visible here. You're creating an assessment
     *      outside of a course context so all this stuff is irrelevant in that usage.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        $context = $this->assessment->getContext();

        /* Assessments directly inside a folder aren't part of a course
         */
        if ($context instanceof Folder\Model) {
            return false;
        }

        /* Implementations must come with a Specification for it to be considered
         * as part of a course. Otherwise you're ramming a new assessment into an
         * already-imported course; this will be considered an implementation but
         * it will have a null specification.
         */
        return $context->isSpecificationContext() || ($context->isImplementationContext() && $this->assessment->getSpecificationAssessment());
    }


    /**
     * Even though you can see the checkbox, you can't always change its value, because
     * you're not allowed to do that. It's managed at a course level.
     *
     * The Course Managed checkbox is mutable if:
     *
     *     - You're a regular joe editing an assessment in an implementation context
     *       and Course Managed is off
     *
     *     - You're a superuser and you're creating/editing an assessment in a specification
     *       or implementation context
     *
     * @return bool
     */
    public function isMutable(): bool
    {
        $context = $this->assessment->getContext();

        /* Assessments directly inside a folder aren't part of a course
         */
        if ($context instanceof Folder\Model) {
            return false;
        }

        /* You're editing an implementation that doesn't have the course managed restriction applied
         * You can freely mutate its special fields as you wish, but the checkbox itself can't be changed
         * since it was defined by the course
         */
        if ($context->isImplementationContext() && $this->assessment->getSpecificationAssessment()) {
            return false;
        }

        /* You're editing a specification context, so you can apply this Course Managed setting and
         * inflict it upon whomever imports your course
         */
        if ($context->isSpecificationContext()) {
            return true;
        }

        return false;
    }


    /**
     * CourseManagedFieldsReadonly decides whether the fields are readonly or not, preventing
     * users from changing their values to ensure consistency across all course imports.
     *
     * CourseManagedFieldsReadonly is true if:
     *
     *     - You're a regular joe editing an assessment in an implementation context and
     *       Course Managed is on
     *
     * @return bool
     */
    public function isReadonly(): bool
    {
        $context = $this->assessment->getContext();

        /* Assessments directly inside a folder aren't part of a course
         */
        if ($context instanceof Folder\Model) {
            return false;
        }

        /* You're editing in an implementation context and the assessment got its fields
         * locked in the course specification, so you can't change stuff...unless you're
         * a superuser, then you can change stuff
         */
        if ($context->isImplementationContext() && $this->assessment->getSpecificationAssessment() && $this->assessment->getCourseManaged()) {
            return ! ( $this->viewer->isSuperuser() || $this->viewer->hasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL));
        }

        return false;
    }
}
