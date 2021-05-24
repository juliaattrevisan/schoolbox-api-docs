<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Validation;

use Schoolbox\Comms\News\Audience;
use Alaress\Specification;

/**
 * Ensures the segment is targeted to an entity value of NULL and that is permitted for the segment's entity type
 */
class ValidNullEntity extends Specification\Leaf
{

    /**
     * The set of entities that can target null, indexed by entityType
     * @var bool[]
     */
    private $entitiesThanCanTargetNull;


    /**
     * @param bool[] $entitiesThanCanTargetNull Indexed by entityType
     */
    public function __construct(array $entitiesThanCanTargetNull)
    {
        $this->entitiesThanCanTargetNull = $entitiesThanCanTargetNull;
    }


    /**
     * @param Audience\Model $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        return null === $candidate->getEntityValue() && \array_key_exists($candidate->getEntityValue(), $this->entitiesThanCanTargetNull);
    }
}
