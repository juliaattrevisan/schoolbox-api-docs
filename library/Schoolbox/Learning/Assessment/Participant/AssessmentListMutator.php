<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Participant;

use Alaress_Date_Format;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;

/**
 * Strategy for filtering out participants who can't view an assessment,
 * and for then filtering the list of assessments.
 *
 * Called a "mutator" because it facilitates both mapping and filtering.
 *
 * Usage:
 * ```
 * $filter = new AssessmentFilter(...);
 * $assessmentCollection
 *     ->map([$filter, 'map'])
 *     ->filter([$filter, 'filter'])
 * ;
 * ```
 */
class AssessmentListMutator
{
    /**
     * @var array<int,bool>
     */
    private array $visibilityCache = [];

    /**
     * Constructor
     *
     * @param User                $user         The user for whom assessments are filtered; usually the logged-in user.
     * @param Visibility          $visibility   Helper class for determining visibility of assessments.
     * @param ExternalUser        $externalUser Helper class for finding children of parent users.
     * @param Alaress_Date_Format $now          The time at which to determine visibility for; usually now, but can be passed in constructor.
     * @param bool                $showClosed   Should closed assessments be shown?
     */
    public function __construct(
        private readonly User $user,
        private readonly Visibility $visibility,
        private readonly ExternalUser $externalUser,
        private readonly Alaress_Date_Format $now,
        private readonly bool $showClosed
    ) {
    }

    /**
     * Callable for mapping an assessment to a version of the assessment with
     * children of `$this->user` who can't view the assessment removed.
     *
     * @param IAssessable $assessment
     * @return IAssessable
     */
    public function map(IAssessable $assessment): IAssessable
    {
        // pre-compute visibility for user check, so that the filter doesn't
        // need to re-check
        $assessmentId = $assessment->getId();
        $this->visibilityCache[$assessmentId] = $this->visibility->isVisibleToUser(
            $assessment,
            $this->user,
            $this->showClosed,
            $this->now
        );

        if ($this->visibilityCache[$assessmentId]) {
            return $assessment;
        }

        if ($this->user->getRole()->isParent()) {
            // Check if the user's children have been loaded, and attempt to load them if not.
            $this->externalUser->loadChildren([$this->user]);

            // Unset children who don't have visibility, and set visibility based on that factor.
            foreach ($this->user->getChildren() as $child) {
                if ($assessment->isParticipant($child)) {
                    if ($this->visibility->isVisibleToUser($assessment, $child, $this->showClosed, $this->now)) {
                        $this->visibilityCache[$assessmentId] = true;
                    } else {
                        $assessment->unsetUserParticipation($child);
                    }
                }
            }
        }

        return $assessment;
    }

    /**
     * Callable for filtering an assessment based on whether `$this->user` or
     * one of their children is participating.
     *
     * Presumes that `$this->map` has been used to remove children of
     * `$this->user` who can't view the assessment.
     *
     * @param IAssessable $assessment
     * @return bool
     */
    public function filter(IAssessable $assessment): bool
    {
        // Use pre-computed visibility if it's present, otherwise exit
        return $this->visibilityCache[$assessment->getId()] ?? false;
    }
}
