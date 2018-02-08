<?php
declare(strict_types=1);

/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant;

use Alaress_Date_Format;
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
     * The user for whom assessments are filtered; usually the logged-in user.
     *
     * @var User
     */
    private $user;

    /**
     * Helper class for determining visibility of assessments.
     *
     * @var Visibility
     */
    private $visibility;

    /**
     * The time at which to determine visibility for; usually now, but can be
     * passed in constructor.
     *
     * @var Alaress_Date_Format
     */
    private $now;

    /**
     * Should closed assessments be shown?
     *
     * @var bool
     */
    private $showClosed;

    /**
     * @var array<int,bool>
     */
    private $visibilityCache = [];

    /**
     * Constructor
     *
     * @param User                $user
     * @param Visibility          $visibility
     * @param Alaress_Date_Format $now
     * @param bool                $showClosed
     */
    public function __construct(User $user, Visibility $visibility, Alaress_Date_Format $now, bool $showClosed)
    {
        $this->user       = $user;
        $this->visibility = $visibility;
        $this->now        = $now;
        $this->showClosed = $showClosed;
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
            $this->showClosed
        );
        if ($this->visibilityCache[$assessmentId]) {
            return $assessment;
        }

        // Unset children who don't have visibility, and set visibility
        // based on that factor
        foreach ($this->user->getChildren() as $child) {
            if ($assessment->isParticipant($child)) {
                if ($this->visibility->isVisibleToLearner($assessment, $child, $this->showClosed, $this->now)) {
                    $this->visibilityCache[$assessmentId] = true;
                } else {
                    $assessment->unsetUserParticipation($child);
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
