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
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Ensures the combination of entity and relationship are allowed
 */
class ValidRelationship extends Specification\Leaf
{

    /** @var string[][] */
    private $validModifiers;


    /**
     * @param string[][] $validModifiers
     */
    public function __construct(array $validModifiers)
    {
        $this->validModifiers = $validModifiers;
    }


    /**
     * @param mixed $candidate
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isSatisfiedBy($candidate): bool
    {
        return \in_array($candidate->getRelationship(), $this->getValidRelationships($candidate), true);
    }


    /**
     * @param Audience\Model $audienceSegment
     * @return array
     * @throws InsufficientHydrationException
     */
    public function getValidRelationships(Audience\Model $audienceSegment): array
    {
        $entityType = $audienceSegment->getEntityType();
        if (null === $entityType) {
            throw new InsufficientHydrationException('Cannot determine valid relationships if entity type is not defined');
        }

        // Base case:
        if (Audience\Segment\Strategy\Composite::getEntityType() !== $entityType) {
            return $this->validModifiers[$entityType];
        }

        // Recursive case:
        // Composites have their own inherent limitations, but also respect the limitations of any sub-segment.
        $validRelationships = $this->validModifiers[$entityType];
        foreach ($audienceSegment->getChildren() as $subSegment) {
            $validRelationships = \array_intersect($validRelationships, $this->getValidRelationships($subSegment));
        }
        return $validRelationships;
    }
}
