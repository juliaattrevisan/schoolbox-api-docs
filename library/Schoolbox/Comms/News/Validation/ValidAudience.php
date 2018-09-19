<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Validation;

use Alaress\Specification;
use Schoolbox\Comms\News;
use Schoolbox\User;

/**
 * Checks that a user is permitted to audience to the articles audience segments
 */
class ValidAudience extends Specification\Leaf
{

    /**
     * Note this is the effector, not the author.
     * The implication being that a user could create an empty draft, then someone else could
     * come along and add audience segments to it which the author was unable to do themselves.
     *
     * @var User
     */
    private $effector;


    public function __construct(User $effector)
    {
        $this->effector = $effector;
    }


    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param mixed|News\Model $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        // TODO: Implement isSatisfiedBy() method.
    }
}
