<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use InvalidArgumentException;
use Schoolbox\Comms\News\Audience\Segment\IStrategy;
use Schoolbox\Comms\News\Audience\Segment\EntityType;

/**
 * Responsible for encapsulating the business rules:
 * 1. which audience segments can be modified to send to parent/teachers of users in the segment.
 * 2. whether it is valid to target a segment to a NULL value, eg 'Users without a SchoolHouse'
 * 3. What relationship modifiers may be applied to a segment of a given entity-type
 *
 * If a segment is composite, then the valid relationships are the intersect of their component segments.
 * eg Audience = user:X && campus:Y. If we can send to user^[S|P|T] and campus^[S|P], then the composite can only send to composite^[S|P]
 * @deprecated
 * @see IStrategy
 */
class BusinessRules
{


    // To ensure efficient db queries, only build query predicates that are valid and can actually match a segment.
    private const VALID_SEGMENT_RELATIONSHIPS = [
        // For composite, this is a superset of what may ultimately be available. Composites cannot have a relationship modifier
        // if any sub-segment does not support that modifier.
        Model::ENTITY_TYPE_COMPOSITE         => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],

        EntityType\User\Strategy::ENTITY_TYPE          => [Model::RELATIONSHIP_SELF],
        EntityType\Super\Strategy::ENTITY_TYPE         => [Model::RELATIONSHIP_SELF],
        EntityType\Campus\Strategy::ENTITY_TYPE        => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\Role\Strategy::ENTITY_TYPE          => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\RoleType\Strategy::ENTITY_TYPE      => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\SchoolHouse\Strategy::ENTITY_TYPE   => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\ResHouse\Strategy::ENTITY_TYPE      => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\Year\Strategy::ENTITY_TYPE          => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\Service\Strategy::ENTITY_TYPE       => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\PastoralGroup\Strategy::ENTITY_TYPE => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
        EntityType\Folder\Strategy::ENTITY_TYPE        => [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF],
    ];

    // Is it valid for segments that target these types to target a null value, eg users
    // * who are not in a school house, or
    // * who are not in any groups.
    private const VALID_SEGMENT_NULL_ENTITIES = [
        Model::ENTITY_TYPE_COMPOSITE                    => true,

        EntityType\User\Strategy::ENTITY_TYPE          => false,
        EntityType\Super\Strategy::ENTITY_TYPE         => false,
        EntityType\Campus\Strategy::ENTITY_TYPE        => true,
        EntityType\Role\Strategy::ENTITY_TYPE          => false,
        EntityType\RoleType\Strategy::ENTITY_TYPE      => false,
        EntityType\SchoolHouse\Strategy::ENTITY_TYPE   => true,
        EntityType\ResHouse\Strategy::ENTITY_TYPE      => true,
        EntityType\Year\Strategy::ENTITY_TYPE          => true,
        EntityType\Service\Strategy::ENTITY_TYPE       => false,
        EntityType\PastoralGroup\Strategy::ENTITY_TYPE => true,
        EntityType\Folder\Strategy::ENTITY_TYPE        => true,
    ];


    /**
     * Returns whether it's valid for an segment to target a user who lacks this entity type
     *
     * @param string $entityType
     * @return bool
     * @deprecated
     * @see IStrategy::canTargetNullEntityValue()
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
     * @deprecated
     * @see IStrategy::getValidRelationshipModifiers()
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
}
