<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use InvalidArgumentException;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Responsible for encapsulating the business rules:
 * 1. which audience segments can be modified to send to parent/teachers of users in the segment.
 * 2. whether it is valid to target a segment to a NULL value, eg 'Users without a SchoolHouse'
 * 3. What relationship modifiers may be applied to a segment of a given entity-type
 *
 * If a segment is composite, then the valid relationships are the intersect of their component segments.
 * eg Audience = user:X && campus:Y. If we can send to user^[S|P|T] and campus^[S|P], then the composite can only send to composite^[S|P]
 *
 */
class BusinessRules
{
    // Maximum node paths from a root segment to a leaf segment
    private const DEPTH_SEGMENT_TREE_MAX = 1;

    // To ensure efficient db queries, only build query predicates that are valid and can actually match a segment.
    private const VALID_SEGMENT_RELATIONSHIPS = [
        // For composite, this is a superset of what may ultimately be available. Composites cannot have a relationship modifier
        // if any sub-segment does not support that modifier.
        Model::ENTITY_TYPE_COMPOSITE      => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],

        Model::ENTITY_TYPE_USER           => [Model::RELATIONSHIP_SELF],
        Model::ENTITY_TYPE_SUPER          => [Model::RELATIONSHIP_SELF],
        Model::ENTITY_TYPE_CAMPUS         => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_ROLE           => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_ROLE_TYPE      => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_SCHOOL_HOUSE   => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_RES_HOUSE      => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_YEAR           => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_SERVICE        => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_PASTORAL_GROUP => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        Model::ENTITY_TYPE_FOLDER         => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
    ];

    // Is it valid for segments that target these types to target a null value, eg users
    // * who are not in a school house, or
    // * who are not in any groups.
    private const VALID_SEGMENT_NULL_ENTITIES = [
        Model::ENTITY_TYPE_USER           => false,
        Model::ENTITY_TYPE_SUPER          => false,
        Model::ENTITY_TYPE_CAMPUS         => true,
        Model::ENTITY_TYPE_ROLE           => false,
        Model::ENTITY_TYPE_ROLE_TYPE      => false,
        Model::ENTITY_TYPE_SCHOOL_HOUSE   => true,
        Model::ENTITY_TYPE_RES_HOUSE      => true,
        Model::ENTITY_TYPE_YEAR           => true,
        Model::ENTITY_TYPE_SERVICE        => false,
        Model::ENTITY_TYPE_PASTORAL_GROUP => true,
        Model::ENTITY_TYPE_FOLDER         => true,
    ];


    /**
     * @return int
     */
    public function getMaxSegmentTreeHeight(): int
    {
        return self::DEPTH_SEGMENT_TREE_MAX;
    }


    /**
     * Returns whether it's valid for an segment to target a user who lacks this entity type
     *
     * @param string $entityType
     * @return bool
     */
    public function canTargetNullEntity(string $entityType): bool
    {
        // Composites always have NULL entity types
        if (Model::ENTITY_TYPE_COMPOSITE === $entityType) {
            return true;
        }
        return self::VALID_SEGMENT_NULL_ENTITIES[$entityType];
    }


    /**
     * Returns the entity types that may use the specified relationship modifier
     *
     * @param string $relationship
     * @return string[]
     * @throws InvalidArgumentException If unable to recognise relationship
     */
    public function getValidEntities(string $relationship): array
    {
        if (!\in_array($relationship, Model::getRelationships(), true)) {
            throw new InvalidArgumentException("Invalid relationship: $relationship");
        }

        $validEntities = [];
        foreach (self::VALID_SEGMENT_RELATIONSHIPS as $entity => $relationships) {
            if (\in_array($relationship, $relationships, true)) {
                $validEntities[] = $entity;
            }
        }
        return $validEntities;
    }


    /**
     * Returns the valid set of relationship modifiers for an audience segment
     *
     * @param Model $audienceSegment
     * @return string[]
     * @throws InsufficientHydrationException If the segment's entity type is not set
     */
    public function getValidRelationships(Model $audienceSegment): array
    {
        $entityType = $audienceSegment->getEntityType();
        if (null === $entityType) {
            throw new InsufficientHydrationException('Cannot determine valid relationships if entity type is not defined');
        }

        $validRelationships = self::VALID_SEGMENT_RELATIONSHIPS[$entityType];

        // Base case:
        if (Model::ENTITY_TYPE_COMPOSITE !== $entityType) {
            return $validRelationships;
        }

        // Recursive case:
        // Composites have their own inherent limitations, but also respect the limitations of any sub-segment.
        foreach ($audienceSegment->getChildren() as $subSegment) {
            $validRelationships = \array_intersect($validRelationships, $this->getValidRelationships($subSegment));
        }
        return $validRelationships;
    }


    /**
     * Returns whether the proposed relationship modifier is valid for an audience segment
     *
     * @param Model  $segment
     * @param string $candidateRelationship
     * @return bool
     * @throws InvalidArgumentException If the relationship is not recognised
     * @throws InsufficientHydrationException
     */
    public function isValidRelationship(Model $segment, string $candidateRelationship): bool
    {
        // Is this an unrecognised relationship (as opposed to a proscribed one)
        if (!\in_array($candidateRelationship, Model::getRelationships(), true)) {
            throw new InvalidArgumentException("Invalid relationship: $candidateRelationship");
        }
        return \in_array($candidateRelationship, $this->getValidRelationships($segment), true);
    }
}
