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

use function array_unique;
use function count;
use function implode;
use function in_array;

class SchoolHouse extends SimpleStrategy
{
    public static function getEntityType(): string
    {
        return 'school_house';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // A user may have no School House
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
        $keywordFilter = null === $searchPlaceholder ? '' : "AND house LIKE :{$searchPlaceholder}";

        $entityType = self::getEntityType();
        return "
            SELECT id, house AS name, '{$entityType}' AS entity_type, null as entity_meta
            FROM house
            WHERE house IS NOT NULL AND deleted_at IS NULL
            {$keywordFilter}
            ORDER BY sequence
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(array $entityValues): TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'house'))
            ->setParamPrefix($entityType)
            ->in('id', $entityValues);

        $sql = "
            SELECT '{$entityType}' entity_type, id entity_value, house name
            FROM house
            WHERE {$pb->getWhereIntersect()}
        ";
        return new TranslateSql($sql, $pb->getParams());
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
        $values      = [];
        $isValidNull = self::isValidNullEntityValue();
        foreach ($users as $user) {
            if ($user->getSchoolHouse() !== null) {
                $values[] = $user->getSchoolHouse()->getId();
            } elseif ($isValidNull) {
                $values[] = null;
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

        $pb = $this->newPb($paramPrefix);
        if ($targetNull) {
            $pb->isNull("$userTableAlias.house");
        }
        if (count($filtered)) {
            $pb->in("$userTableAlias.house", $filtered);
        }
        $pb = $this->newPb($paramPrefix)->any($pb);

        return new UserResolutionSql('', $pb);
    }
}
