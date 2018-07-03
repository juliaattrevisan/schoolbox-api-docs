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
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Ensures the combination of entity and relationship are allowed
 */
class ValidRelationship extends Specification\Leaf
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
     * @throws InsufficientHydrationException
     */
    public function isSatisfiedBy($candidate): bool
    {
        return \in_array($candidate->getRelationship(), $this->businessRules->getValidRelationships($candidate), true);
    }
}
