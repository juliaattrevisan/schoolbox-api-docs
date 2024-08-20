<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Quoter\Mysql\Quoter;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use UnderflowException;

use function array_unique;
use function count;
use function implode;
use function in_array;

class Campus extends SimpleStrategy
{
    /**
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'campus';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // From a schema level, a user has 0:N campus
        return false;
//        return true; // TODO Turned off as not-yet implemented in front-end so no need to include in query
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
            FROM luCampus
            WHERE name IS NOT NULL AND deleted_at IS NULL
            {$keywordFilter}
            ORDER BY sequence
        ";
    }


    /** @inheritdoc */
    public function getTranslateSql(array $entityValues): Audience\Segment\TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'luCampus'))
            ->setParamPrefix($entityType)
            ->in('id', $entityValues);

        return new Audience\Segment\TranslateSql(
            "SELECT '{$entityType}' entity_type, id entity_value, name name FROM luCampus WHERE {$pb->getWhereIntersect()}",
            $pb->getParams()
        );
    }

    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        return [Audience\Model::RELATIONSHIP_SELF];
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
        $values      = [];
        $isValidNull = self::isValidNullEntityValue();
        foreach ($users as $user) {
            $campuses = $user->getCampusCollection();
            if (!count($campuses)) {
                if ($isValidNull) {
                    $values[] = null;
                }
            } else {
                foreach ($campuses as $campus) {
                    $values[] = (string)$campus->getId();
                }
            }
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

        $userCampusAlias = "uc_$paramPrefix";
        $joinType        = $targetNull ? "LEFT JOIN" : "JOIN";
        $join            = "$joinType userCampus $userCampusAlias ON $userTableAlias.id = $userCampusAlias.uid";

        $pb = $this->newPb($paramPrefix);
        if ($targetNull) {
            $pb->isNull("$userCampusAlias.uid");
        }
        if (count($filtered)) {
            $pb->in("$userCampusAlias.campus", $filtered);
        }
        $pb = $this->newPb($paramPrefix)->any($pb);

        return new UserResolutionSql($join, $pb);
    }
}
