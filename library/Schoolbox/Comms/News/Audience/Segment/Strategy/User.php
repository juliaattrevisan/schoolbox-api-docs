<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\Mysql\Quoter;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\TranslateSql;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\Role;

use function implode;

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
        if ($entityMeta === null || $entityMeta === Role::STUDENT) {
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
        $keywordFilter = null === $searchPlaceholder
            ? ''
            : "AND (firstname LIKE :{$searchPlaceholder} OR preferred_name LIKE :{$searchPlaceholder} OR lastname LIKE :{$searchPlaceholder})";

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
    public function getTranslateSql(array $entityValues): TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'user'))
            ->setParamPrefix($entityType)
            ->in('id', $entityValues);

        $sql = "
            SELECT '{$entityType}' entity_type, id entity_value, CONCAT(firstname, ' ', lastname) name
            FROM user
            WHERE {$pb->getWhereIntersect()}
        ";
        return new TranslateSql($sql, $pb->getParams());
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


    /** @inheritDoc */
    public function getUserResolutionSql(
        string $userTableAlias,
        string $paramPrefix,
        array $entityValues,
        bool $excludeMuted
    ): UserResolutionSql {
        // no need for join or to filter out null values (all users have IDs)
        $join = '';
        $pb   = $this->newPb($paramPrefix)->in("{$userTableAlias}.id", $entityValues);
        return new UserResolutionSql($join, $pb);
    }
}
