<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

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
    public function getAudiencingSql(\Schoolbox\User $user, string $searchPlaceholder = null): string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND (firstname LIKE :{$searchPlaceholder} OR lastname LIKE :{$searchPlaceholder})";

        $entityType = self::getEntityType();
        return "
            SELECT id, CONCAT(firstname, ' ', lastname) AS name, '{$entityType}' AS entity_type
            FROM `user`
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
}
