<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use InvalidArgumentException;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

/**
 * Authorisation rule to check whether an assessment is locked.
 *
 * Does NOT short-circuit for SuperUsers - the module should combine Rules to achieve that effect if desired.
 */
abstract class IsUnLocked implements IAuthorizationRule
{
    /** @var LockedChecker */
    private $lockedChecker;

    /** @var IAssessable $assessment */
    private $assessment;

    /**
     * @param LockedChecker $lockedChecker
     * @param IAssessable|null $assessable Optional assessment override
     */
    public function __construct(LockedChecker $lockedChecker, IAssessable $assessable = null)
    {
        $this->lockedChecker = $lockedChecker;
        $this->assessment    = $assessable;
    }

    /**
     * Ensures the specified assessment is not locked from proposed action (modify or interaction)
     *
     * Does NOT check for SuperUser status - that is module's responsibility when combining rules.
     *
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException If assessment's due date is null
     * @throws Unauthorized If assessment is locked
     */
    public function authorize(array $params = [], User $user = null)
    {
        if ($this->assessment instanceof IAssessable) {
            $assessment = $this->assessment;
        } else {
            $assessment = $this->getAssessment($params);
        }

        if ($this->isLocked($assessment)) {
            throw new Unauthorized();
        }
    }

    /**
     * @param IAssessable $assessment
     * @return bool
     * @throws InvalidArgumentException If assessment is not an assessment spec and due date is null.
     */
    abstract protected function isLocked(IAssessable $assessment);

    /**
     * Find the assessment in the hydrated params
     *
     * @param array $params
     * @return IAssessable
     * @throws InvalidArgumentException If unable to locate an assessment object
     */
    private function getAssessment(array $params)
    {
        // Was an assessment supplied to the constructor?

        if ($this->assessment) {
            return $this->assessment;
        }

        // Was an assessment injected?

        if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
            return $params['assessment'];
        }

        // Is a participant available?

        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getAssessable();
        }

        throw new InvalidArgumentException("Unable to locate an 'assessment' instance in params.");
    }

    // Getters & Setters

    /**
     * @return LockedChecker
     */
    protected function getLockedChecker()
    {
        return $this->lockedChecker;
    }
}
