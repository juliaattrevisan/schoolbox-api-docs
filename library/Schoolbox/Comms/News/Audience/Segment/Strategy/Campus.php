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
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $entityType = self::getEntityType();
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, luCampus.name
            FROM comms_news_audience cna
            INNER JOIN luCampus ON cna.entity_type = '{$entityType}' AND cna.entity_value = luCampus.id
            WHERE cna.id IN ($segmentIdsPlaceholder) AND luCampus.deleted_at IS NULL
        ";
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
        $values = [];
        foreach ($users as $user) {
            $campuses = $user->getCampusCollection();
            if (!\count($campuses)) {
                $values[] = null;
            } else {
                foreach ($campuses as $campus) {
                    $values[$campus->getId()] = $campus->getId();
                }
            }
        }
        return $values;
    }
}
