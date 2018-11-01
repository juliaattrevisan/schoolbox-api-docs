<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
use Schoolbox\User;

class PastoralGroup extends SimpleStrategy
{

    public static function getEntityType(): string
    {
        return 'pastoral_group';
    }


    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // A user may not belong to any pastoral groups
        return true;
    }


    /**
     * @inheritdoc
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): ?string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND `name` LIKE :{$searchPlaceholder}";

        // Note that we need to collate the output so the result set can be UNIONed with other result sets
        $entityType = self::getEntityType();
        return "
            SELECT id, name COLLATE utf8mb4_general_ci, '{$entityType}' AS entity_type
            FROM `pastoral_group`
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
            SELECT DISTINCT cna.entity_type, cna.entity_value, pastoral_group.`name` COLLATE utf8mb4_general_ci AS `name`
            FROM comms_news_audience cna
            INNER JOIN pastoral_group ON entity_type = '{$entityType}' AND cna.entity_value = pastoral_group.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(): array
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
}
