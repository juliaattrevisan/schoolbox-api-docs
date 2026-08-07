<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;

/**
 * Authorizes a specific participant's activity without granting access to its folder.
 */
class CanViewParticipantActivity extends AbstractParticipationRule
{
    /** @var Visibility */
    private $visibility;

    /** @var bool */
    private $showClosed;

    /** @var Alaress_Date_Format|null */
    private $now;

    /**
     * Configures participant-aware activity visibility for a summary route.
     *
     * @param Visibility $visibility Activity visibility policy.
     * @param bool $showClosed Whether closed activities remain visible.
     * @param Alaress_Date_Format|null $now Optional fixed time for deterministic checks.
     */
    public function __construct(Visibility $visibility, bool $showClosed = true, ?Alaress_Date_Format $now = null)
    {
        $this->visibility = $visibility;
        $this->showClosed = $showClosed;
        $this->now        = $now;
    }

    /**
     * Authorizes visibility of the selected student's activity for the logged-in viewer.
     *
     * @param array $params Preloaded route parameters containing the assessment and student.
     * @param User|null $user Logged-in viewer.
     * @return void
     * @throws InvalidArgumentException If the assessment or student is absent from the route parameters.
     * @throws Unauthorized If there is no viewer or the participant activity is not visible.
     */
    public function authorize(array $params = [], ?User $user = null): void
    {
        if ($user === null) {
            throw new Unauthorized('A user is required to perform this auth check');
        }

        $assessable = $this->findAssessable($params);
        $student    = $this->findStudent($params);

        if (
            $this->visibility->isVisibleToParticipantActivity(
                $assessable,
                $user,
                $student,
                $this->showClosed,
                $this->now
            )
        ) {
            return;
        }

        throw new Unauthorized(sprintf(
            'User %s is not permitted to see assessment %s for learner id %s; check parameters and identifiers.',
            $user->getId(),
            $assessable->getId(),
            $student->getId()
        ));
    }
}
