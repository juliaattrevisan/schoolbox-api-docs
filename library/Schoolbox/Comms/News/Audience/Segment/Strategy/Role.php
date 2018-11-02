<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class Role extends SimpleStrategy
{
    public static function getEntityType(): string
    {
        return 'role';
    }


    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // All users have a role
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(): array
    {
        return [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF];
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityType(User $user): bool
    {
        if (parent::canAudienceToEntityType($user)) {
            return true;
        }
        return $this->getStaffCanPost() && $user->getRole()->isStaff();
    }

    /**
     * @inheritdoc
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): ?string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND `name` LIKE :{$searchPlaceholder}";

        $entityType = self::getEntityType();
        return "
            SELECT id, name, '{$entityType}' AS entity_type
            FROM groups
            WHERE deleted_at IS NULL
            {$keywordFilter}
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $entityType = self::getEntityType();
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, groups.name AS `name`
            FROM comms_news_audience cna
            INNER JOIN groups ON cna.entity_type = '{$entityType}' AND cna.entity_value = groups.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }

    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user): string
    {
        return '';
    }


    /**
     * @inheritdoc
     * @param GenericCollection|User[] $users
     */
    public function getValues(GenericCollection $users): array
    {
        $values = [];
        foreach ($users as $user) {
            $values[$user->getRole()->getId()] = $user->getRole()->getId();
        }
        return $values;
    }
}
