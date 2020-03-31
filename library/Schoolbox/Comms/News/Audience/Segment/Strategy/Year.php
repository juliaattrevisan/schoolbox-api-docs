<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use function array_filter;

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
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $entityType = self::getEntityType();
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) AS name
            FROM comms_news_audience cna
            INNER JOIN `user` ON cna.entity_type = '{$entityType}' AND cna.entity_value = `user`.year
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
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
}
