<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\GenericCollection;
use Schoolbox\Role;

class User extends SimpleStrategy
{

    /**
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'user';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // All users are users
        return false;
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityType(\Schoolbox\User $user): bool
    {
        if (parent::canAudienceToEntityType($user)) {
            return true;
        }
        return $this->getStaffCanPost() && $user->getRole()->isStaff();
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        if($entityMeta === null || $entityMeta === Role::STUDENT) {
            return [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF, Model::RELATIONSHIP_TEACHERS_OF];
        } else {
            return [Model::RELATIONSHIP_SELF];
        }
    }


    /**
     * @inheritdoc
     */
    public function getAudiencingSql(\Schoolbox\User $user, string $searchPlaceholder = null): ?string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND (firstname LIKE :{$searchPlaceholder} OR lastname LIKE :{$searchPlaceholder})";

        $entityType = self::getEntityType();
        return "
            SELECT
                user.id,
                TRIM(CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, ''), ' (', `groups`.name, ')')) AS name,
                '{$entityType}' AS entity_type,
                `groups`.group_type as entity_meta
            FROM `user` 
            INNER JOIN `groups` ON user.group_id = `groups`.id
            WHERE user.deleted_at IS NULL
            AND `groups`.name IS NOT NULL
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
            SELECT DISTINCT cna.entity_type, cna.entity_value, CONCAT(firstname, ' ', lastname) AS `name`
            FROM comms_news_audience cna
            INNER JOIN `user` ON cna.entity_type = '{$entityType}' AND cna.entity_value = `user`.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }

    /**
     * @inheritdoc
     */
    public function getSearchSql(\Schoolbox\User $user): string
    {
        return '';
    }


    /**
     * @param GenericCollection|User[] $users
     * @return array
     */
    public function getValues(GenericCollection $users): array
    {
        return \array_unique($users->getPropertyValues('id'));
    }
}
