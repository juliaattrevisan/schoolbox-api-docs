<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\Mysql\Quoter;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\TranslateSql;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use UnderflowException;

use function array_unique;
use function count;
use function implode;
use function in_array;

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
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        return [Model::RELATIONSHIP_SELF];
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
            SELECT id, name, '{$entityType}' AS entity_type, null as entity_meta
            FROM `groups`
            WHERE deleted_at IS NULL
            AND name IS NOT NULL
            {$keywordFilter}
            ORDER BY `sequence` ASC
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(array $entityValues): TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'groups'))
            ->setParamPrefix($entityType)
            ->in('id', $entityValues);

        $sql = "
            SELECT '{$entityType}' entity_type, id entity_value, name name
            FROM `groups`
            WHERE {$pb->getWhereIntersect()}
        ";
        return new TranslateSql($sql, $pb->getParams());
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
            $values[] = $user->getRole()->getId();
        }
        return array_unique($values);
    }


    /**
     * @inheritDoc
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    public function getUserResolutionSql(
        string $userTableAlias,
        string $paramPrefix,
        array $entityValues,
        bool $excludeMuted
    ): UserResolutionSql {
        $targetNull = self::isValidNullEntityValue() && in_array(null, $entityValues, true);
        $filtered   = $this->filterOutNullValues($entityValues);
        if (!$targetNull && !count($filtered)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        return new UserResolutionSql('', $this->newPb($paramPrefix)->in("$userTableAlias.group_id", $filtered));
    }
}
