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
use Schoolbox\Comms\News\Audience\Segment\TranslateSql;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use UnderflowException;

use function array_filter;
use function count;
use function implode;
use function in_array;

class Year extends SimpleStrategy
{
    public static function getEntityType(): string
    {
        return 'year';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // A user may have no year
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
        // If user.year is numeric, prefix 'Year ' to read like 'Year 9'
        $entityType = self::getEntityType();

        $keywordFilter = '';
        if ($searchPlaceholder !== null) {
            /**
             * because the column values for years are typically stored as a number, we want to make it easy for users
             * to search for either: "9" or "year 9" and get the same results
             */
            $keywordFilter = "AND (lu_year_level.name LIKE :{$searchPlaceholder} or concat('{$entityType} ', lu_year_level.name) LIKE :{$searchPlaceholder})";
        }

        return "
            SELECT DISTINCT
              lu_year_level.name AS id,
              IF(lu_year_level.name REGEXP '^[0-9]+$', CONCAT('Year ', lu_year_level.name), lu_year_level.name) AS name,
              '{$entityType}' AS entity_type,
              null as entity_meta
            FROM lu_year_level
            WHERE lu_year_level.deleted_at IS NULL
            AND lu_year_level.name IS NOT NULL
            {$keywordFilter}
            ORDER BY lu_year_level.sequence
        ";
    }

    /**
     * @inheritdoc
     */
    public function getTranslateSql(array $entityValues): TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'lu_year_level'))
            ->setParamPrefix($entityType)
            ->in('lu_year_level.name', $entityValues);

        $sql = "
            SELECT
                '{$entityType}' entity_type,
                lu_year_level.name AS entity_value,
                IF(lu_year_level.name REGEXP '^[0-9]+$', CONCAT('Year ', lu_year_level.name), lu_year_level.name) name
            FROM lu_year_level
            WHERE {$pb->getWhereIntersect()}
        ";

        return new TranslateSql(
            $sql,
            $pb->getParams()
        );
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
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
        /* Users now come with a Year/Model instance inside
         */
        $userYears = $users->getPropertyValues('year');

        /* We need to get the name out, because that's what it was using before
         *
         * Apparently NULL was an anticipated value with an optional via isValidNullEntityValue()
         * but that's hardcoded to return false all the time, so god knows what this was
         * meant to actually do (if anything) at all. Nevertheless, accommodate the original intent
         */
        $yearLevels = [];

        foreach ($userYears as $userYear) {
            if ($userYear instanceof \Schoolbox\YearLevel\Model) {
                $yearLevels[] = $userYear->getName();
            } else {
                $yearLevels[] = null;
            }
        }

        /* This is always going to execute, so whatever this was originally doing,
         * it'll continue to do
         */
        if (!self::isValidNullEntityValue()) {
            $yearLevels = array_filter($yearLevels, static function (?string $yearLevel): bool {
                return $yearLevel !== null;
            });
        }
        return \array_unique($yearLevels);
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

        $pb = $this->newPb($paramPrefix);
        if ($targetNull) {
            $pb->isNull("$userTableAlias.year_id");
        }
        if (count($filtered)) {
            $pb->in("lyl.name", $filtered);
        }
        $pb = $this->newPb($paramPrefix)->any($pb);

        return new UserResolutionSql('', $pb);
    }
}
