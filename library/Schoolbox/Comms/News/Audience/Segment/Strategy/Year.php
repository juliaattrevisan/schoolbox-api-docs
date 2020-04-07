<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\Mysql\Quoter;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\TranslateSql;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\User;
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
        $keywordFilter = null === $searchPlaceholder ? '' : "AND year LIKE :{$searchPlaceholder}";

        // If user.year is numeric, prefix 'Year ' to read like 'Year 9'
        $entityType = self::getEntityType();
        return "
            SELECT DISTINCT
              year AS id,
              IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) AS name,
              '{$entityType}' AS entity_type,
              null as entity_meta
            FROM `user`
            WHERE deleted_at IS NULL
            AND year IS NOT NULL
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

        $whereClause = implode(' AND ', $pb->getWhere());
        $sql         = "
            SELECT
                '{$entityType}' entity_type,
                id entity_value,
                IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) name
            FROM user WHERE {$whereClause}
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
        $yearLevels = $users->getPropertyValues('year');
        if (!self::isValidNullEntityValue()) {
            $yearLevels = array_filter($yearLevels, static function (?string $yearLevel): bool {
                return $yearLevel !== null;
            });
        }
        return \array_unique($yearLevels);
    }


    /** @inheritDoc */
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
            $pb->isNull("$userTableAlias.year");
        }
        if (count($filtered)) {
            $pb->in("$userTableAlias.year", $filtered);
        }
        $pb = $this->newPb($paramPrefix)->any($pb);

        return new UserResolutionSql('', $pb);
    }


    /**
     * @inheritDoc
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool
    {
        switch($relationship){
            case Audience\Model::RELATIONSHIP_SELF:
                if($user->getYear() === null){
                    return self::isValidNullEntityValue() && in_array(null, $entityValues, true);
                }
                return in_array($user->getYear(), $entityValues, false);
            case Audience\Model::RELATIONSHIP_PARENTS_OF:
            case Audience\Model::RELATIONSHIP_TEACHERS_OF:
                return null;
        }
        return null;
    }
}
