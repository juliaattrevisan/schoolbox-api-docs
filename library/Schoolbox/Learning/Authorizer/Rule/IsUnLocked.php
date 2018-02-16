<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\User;

/**
 * Authorisation rule to check whether an assessment is locked.
 *
 * Does NOT short-circuit for SuperUsers - the module should combine Rules to achieve that effect if desired.
 */
abstract class IsUnLocked extends AbstractAssessableRule
{
    /** @var LockedChecker */
    private $lockedChecker;

    /**
     * @param LockedChecker $lockedChecker
     */
    public function __construct(LockedChecker $lockedChecker)
    {
        $this->lockedChecker = $lockedChecker;
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
        if ($this->isLocked($this->findAssessable($params))) {
            throw new Unauthorized();
        }
    }

    /**
     * @param IAssessable $assessment
     * @return bool
     * @throws InvalidArgumentException If assessment is not an assessment spec and due date is null.
     */
    abstract protected function isLocked(IAssessable $assessment);


    // Getters & Setters

    /**
     * @return LockedChecker
     */
    protected function getLockedChecker()
    {
        return $this->lockedChecker;
    }
}
