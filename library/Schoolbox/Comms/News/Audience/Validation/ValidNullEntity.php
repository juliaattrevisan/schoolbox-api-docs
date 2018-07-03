<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Validation;

use Schoolbox\Comms\News\Audience;
use Specification;

/**
 * Ensures the segment is targeted to an entity value of NULL and that is permitted for the segment's entity type
 */
class ValidNullEntity extends Specification\Leaf
{

    /** @var Audience\BusinessRules */
    private $businessRules;


    /**
     * @param Audience\BusinessRules $businessRules
     */
    public function __construct(Audience\BusinessRules $businessRules)
    {
        $this->businessRules = $businessRules;
    }


    /**
     * @param Audience\Model $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        return null === $candidate->getEntityValue() && $this->businessRules->canTargetNullEntity($candidate->getEntityType());
    }
}
