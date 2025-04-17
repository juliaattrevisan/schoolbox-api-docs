<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Alaress_Date_Format;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Badge;
use Schoolbox\Learning\Badge\Award;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method Model[] findByColumn($columnName, $columnValue, array $options = [])
 * @method Model[] findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * The minimum amount of characters required when searching for a user
     */
    private const MIN_CHARACTER_LIMIT = 2;

    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('badge_user');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('recipient_id', UserMapper::class, 'id', null, null, 'recipient', 'recipient');
        $this->joins('badge_id', \Schoolbox\Learning\Badge\Mapper::class, 'id', null, null, 'badge', 'badge');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('reason', self::TYPE_STRING);
        $this->joins('reasoned_by', UserMapper::class, 'id', null, null, 'reasonedBy', 'reasonedBy');
        $this->uses('reasoned_at', self::TYPE_DATETIME);
        $this->joins('awarded_by', UserMapper::class, 'id', null, null, 'awardedBy', 'awardedBy');
        $this->uses('awarded_at', self::TYPE_DATETIME);
        $this->uses('expires_at', self::TYPE_DATE);
        $this->uses('viewed_on', self::TYPE_DATETIME);
        $this->joins('award_id', static::class, 'id', null, null, 'award', 'award');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('recipient', 'r')},
                {$this->getJoinColumnString('recipient.role', 'rr')},
                {$this->getJoinColumnString('recipient.pronoun', 'rp')},
                {$this->getJoinColumnString('awardedBy', 'a')},
                {$this->getJoinColumnString('awardedBy.role', 'ar')},
                {$this->getJoinColumnString('awardedBy.pronoun', 'ap')},
                {$this->getJoinColumnString('reasonedBy', 'rb')},
                {$this->getJoinColumnString('reasonedBy.role', 'rbr')},
                {$this->getJoinColumnString('reasonedBy.pronoun', 'rbp')},
                {$this->getJoinColumnString('award', 'aw')},
                {$this->getJoinColumnString('award.badge', 'awb')},
                {$this->getJoinColumnString('award.recipient', 'awr')},
                {$this->getJoinColumnString('award.recipient.role', 'awrr')},
                {$this->getJoinColumnString('award.recipient.pronoun', 'awrp')},
                {$this->getJoinColumnString('award.awardedBy', 'awa')},
                {$this->getJoinColumnString('award.awardedBy.role', 'awar')},
                {$this->getJoinColumnString('award.awardedBy.pronoun', 'awap')},
                {$this->getJoinColumnString('award.reasonedBy', 'awrb')},
                {$this->getJoinColumnString('award.reasonedBy.role', 'awrbr')},
                {$this->getJoinColumnString('award.reasonedBy.pronoun', 'awrbp')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('recipient', '', 'r', true)}
            INNER JOIN {$this->getJoinSql('recipient.role', 'r', 'rr', true)}
            LEFT JOIN {$this->getJoinSql('recipient.pronoun', 'r', 'rp')}
            INNER JOIN {$this->getJoinSql('awardedBy', '', 'a', true)}
            INNER JOIN {$this->getJoinSql('awardedBy.role', 'a', 'ar', true)}
            LEFT JOIN {$this->getJoinSql('awardedBy.pronoun', 'a', 'ap')}
            LEFT  JOIN {$this->getJoinSql('reasonedBy', '', 'rb', true)}
            LEFT  JOIN {$this->getJoinSql('reasonedBy.role', 'rb', 'rbr', true)}
            LEFT  JOIN {$this->getJoinSql('reasonedBy.pronoun', 'rb', 'rbp')}
            LEFT  JOIN {$this->getJoinSql('award', '', 'aw')}
            LEFT  JOIN {$this->getJoinSql('award.badge', 'aw', 'awb')}
            LEFT  JOIN {$this->getJoinSql('award.recipient', 'aw', 'awr', true)}
            LEFT  JOIN {$this->getJoinSql('award.recipient.role', 'awr', 'awrr', true)}
            LEFT  JOIN {$this->getJoinSql('award.recipient.pronoun', 'awr', 'awrp')}
            LEFT  JOIN {$this->getJoinSql('award.awardedBy', 'aw', 'awa', true)}
            LEFT  JOIN {$this->getJoinSql('award.awardedBy.role', 'awa', 'awar', true)}
            LEFT  JOIN {$this->getJoinSql('award.awardedBy.pronoun', 'awa', 'awap')}
            LEFT  JOIN {$this->getJoinSql('award.reasonedBy', 'aw', 'awrb', true)}
            LEFT  JOIN {$this->getJoinSql('award.reasonedBy.role', 'awrb', 'awrbr', true)}
            LEFT  JOIN {$this->getJoinSql('award.reasonedBy.pronoun', 'awrb', 'awrbp')}
        ";
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws MapperException
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $badgeMapper = Badge\Mapper::getInstance();
        $rows        = $statement->fetchAll();

        // Create array of badgeIds, de-duping as we go.
        $badgeIdsAsKeys = [];
        foreach ($rows as $row) {
            $badgeIdsAsKeys[$row['badge_id']] = null;    // Store as keys so we de-dupe as we store.
            $badgeIdsAsKeys[$row['aw.badge_id']] = null; // Store as keys so we de-dupe as we store.
        }

        // Create array of badges, indexed by badgeId.
        $badgeById = [];
        foreach (
            // Note regarding soft-deletion inconsistencies between parent and child records
            // -----------------------------------------------------------------------------
            // Include deleted badges here, because the query in $statement may not exclude non-soft-deleted badge_user
            // rows that have soft-deleted badge rows as parents.  This situation should not happen, but it has happened
            // in the past due to 'UPDATE badge SET deleted_at=NOW()' commands being run manually without badge_user.deleted_at
            // being updated similarly.
            //    If we do not include deleted badges here, then when we try to use $badgeById below to get the badge
            // with a particular ID (sourced from $statement), we may find that no matching entry exists in $badgeById.
            $badgeMapper->findByColumnList(['id' => array_keys($badgeIdsAsKeys)], array('deleted' => true)) as $badge
        ) {
            $badgeById[$badge->getId()] = $badge;
        }

        $collection = $this->createCollection();

        /* A lookup, because badge hydration is a dog
         */
        foreach ($rows as $row) {
            $award = $this->hydrate($row);

            /* Set the $recipient and also their role
             */
            $recipient = $this->hydrateJoin($row, 'recipient', 'r');

            if ($recipient instanceof User) {
                $role = $this->hydrateJoin($row, 'recipient.role', 'rr');

                if ($role instanceof Role) {
                    $recipient->setRole($role);
                }
                $recipientPronoun = $this->hydrateJoin($row, 'recipient.pronoun', 'rp');
                $recipient->setPronoun($recipientPronoun);
                $award->setRecipient($recipient);
            }

            /* Set the $awardedBy and also their role
             */
            $awardedBy = $this->hydrateJoin($row, 'awardedBy', 'a');

            if ($awardedBy instanceof User) {
                $role = $this->hydrateJoin($row, 'awardedBy.role', 'ar');

                if ($role instanceof Role) {
                    $awardedBy->setRole($role);
                }

                $awardedByPronoun = $this->hydrateJoin($row, 'awardedBy.pronoun', 'ap');
                $awardedBy->setPronoun($awardedByPronoun);

                $award->setAwardedBy($awardedBy);
            }

            /* Set the $reasonedBy and also their role
             */
            $reasonedBy = $this->hydrateJoin($row, 'reasonedBy', 'rb');

            if ($reasonedBy instanceof User) {
                $role = $this->hydrateJoin($row, 'reasonedBy.role', 'rbr');

                if ($role instanceof Role) {
                    $reasonedBy->setRole($role);
                }

                $reasonedByPronoun = $this->hydrateJoin($row, 'reasonedBy.pronoun', 'rbp');
                $reasonedBy->setPronoun($reasonedByPronoun);

                $award->setReasonedBy($reasonedBy);
            }

            $award->setBadge($badgeById[$row['badge_id']]);

            // If there is a parent award:
            // Hydrate it, and its awarded by, reasoned by, recipient, and badge.
            if ($row['aw.id']) {
                $parentAward = $this->hydrateJoin($row, 'award', 'aw');
                if ($parentAward instanceof Model) {
                    $parentAwardedBy = $this->hydrateJoin($row, 'award.awardedBy', 'awa');
                    if ($parentAwardedBy instanceof User) {
                        $parentAwardedByRole = $this->hydrateJoin($row, 'award.awardedBy.role', 'awar');
                        if ($parentAwardedByRole instanceof Role) {
                            $parentAwardedBy->setRole($parentAwardedByRole);
                        }
                        $parentAwardedByPronoun = $this->hydrateJoin($row, 'award.awardedBy.pronoun', 'awap');
                        $parentAwardedBy->setPronoun($parentAwardedByPronoun);
                        $parentAward->setAwardedBy($parentAwardedBy);
                    }

                    $parentReasonedBy = $this->hydrateJoin($row, 'award.reasonedBy', 'awrb');
                    if ($parentReasonedBy instanceof User) {
                        $parentReasonedByRole = $this->hydrateJoin($row, 'award.reasonedBy.role', 'awrbr');
                        if ($parentReasonedByRole instanceof Role) {
                            $parentReasonedBy->setRole($parentReasonedByRole);
                        }
                        $parentReasonedByPronoun = $this->hydrateJoin($row, 'award.reasonedBy.pronoun', 'awrbp');
                        $parentReasonedBy->setPronoun($parentReasonedByPronoun);
                        $parentAward->setReasonedBy($parentReasonedBy);
                    }

                    // Parent recipient will usually be the same as this award's:
                    // however, don't assume that
                    $parentRecipient = $this->hydrateJoin($row, 'award.recipient', 'awr');
                    if ($parentRecipient instanceof User) {
                        $parentRecipientRole = $this->hydrateJoin($row, 'award.recipient.role', 'awrr');
                        if ($parentRecipientRole instanceof Role) {
                            $parentRecipient->setRole($parentRecipientRole);
                        }
                        $parentRecipientPronoun = $this->hydrateJoin($row, 'award.recipient.pronoun', 'awrp');
                        $parentRecipient->setPronoun($parentRecipientPronoun);
                        $parentAward->setRecipient($parentRecipient);
                    }

                    $parentAward->setBadge($badgeById[$row['aw.badge_id']]);

                    $award->setAward($parentAward);
                }
            }

            $collection->add($award);
        }

        return $collection;
    }


    /**
     * Find all awarded badges
     *
     * @param User                     $user
     * @param string                   $awardStatus
     * @param Alaress_Date_Format|null $expiryDate  If provided, only get awards
     *                                              which aren't expired by this
     *                                              date.
     * @return GenericCollection
     */
    public function findForUser(
        User $user,
        string $awardStatus,
        ?Alaress_Date_Format $expiryDate
    ): GenericCollection {
        /* Ensure $awardStatus is one we allow; empty collection returned if invalid
         */
        if (!in_array($awardStatus, [ Model::AWARD_STATUS_GOAL, Model::AWARD_STATUS_ACHIEVEMENT ], true)) {
            return $this->createCollection();
        }

        /* Otherwise get the $awardStatus records
         */
        $to = Alaress_Date_Format::now();

        $pb = $this->getPredicateBuilder()
            ->equals('recipient_id', $user->getId())
            ->equals('status', $awardStatus)
            ->isNull('award_id')
            ->betweenDates('awarded_at', null, $to);
        if ($expiryDate) {
            $pb = $pb->any(
                $this->getPredicateBuilder()
                     ->isNull('expires_at')
                     ->greaterThanOrEqualTo('expires_at', $expiryDate->getDatabaseDate())
            );
        }

        return $this->findByPredicates($pb, [
            'order' => [
                'awarded_at' => 'desc'
            ]
        ]);
    }


    /**
     * Find awards in specific badge categories given to users
     * Logic should be similar to $this->findForUser
     * @param int[]    $userIds
     * @param int[]    $categoryIds
     * @param string[] $status
     * @param int[]    $termIds
     * @return GenericCollection|Model[]
     * @throws MapperException
     */
    public function findForUsers(array $userIds, array $categoryIds, array $status, array $termIds): Collection
    {
        $pb = $this->getPredicateBuilder();
        if (!empty($categoryIds)) {
            $pb = $pb->in('b.category_id', $categoryIds);
        }
        if (!empty($status)) {
            $pb = $pb->in('status', $status);
        }
        if (!empty($userIds)) {
            $pb->in('recipient_id', $userIds);
        }
        $pb = $pb->isNull('award_id');
        $pb = $pb->isNull('deleted_at');

        // Terms .. attempt to get simplified ranges from given terms
        $terms = \Schoolbox\Assessment\Term\Mapper::getInstance()->findByColumnList(['id' => $termIds])->sortBy('start', Collection::ORDER_ASCENDING);
        // Examined time ranges
        $ranges = [];
        /** @var \Schoolbox\Assessment\Term\Model $term */
        foreach ($terms as $term) {
            if (empty($ranges)) {
                // set first term as an examined range
                $ranges[] = [
                    'from' => $term->getStart(),
                    'to'   => $term->getActualFinish(),
                ];
                continue;
            }

            $newRange = true;

            // Check whether the current term is in the examined ranges
            foreach ($ranges as $key => $range) {
                /** @var Alaress_Date_Format $rangeFrom */
                $rangeFrom = $range['from'];
                /** @var Alaress_Date_Format|null $rangeTo */
                $rangeTo = $range['to'];

                // The term does not have overlap time.
                if (
                    ($term->getActualFinish() && $term->getActualFinish()->diffInDays($rangeFrom) < -1)
                    || ($rangeTo && $term->getStart()->diffInDays($rangeTo) > 1)
                ) {
                    continue;
                }
                $newRange = false;
                // if the current term (likely be a transition term) overlaps existing term
                // and current start is before existing start
                if ($term->getStart()->lt($rangeFrom)) {
                    $ranges[$key]['from'] = $term->getStart();
                }
                // and current end is later than existing end
                if (!$term->getActualFinish() || ($rangeTo && $term->getActualFinish()->gt($rangeTo))) {
                    $ranges[$key]['to'] = $term->getActualFinish();
                }
            }
            if ($newRange) {
                $ranges[] = [
                    'from' => $term->getStart(),
                    'to'   => $term->getActualFinish(),
                ];
            }
        }
        $pbTerms = $this->getPredicateBuilder();
        foreach ($ranges as $range) {
            $pbTerm = $this->getPredicateBuilder();
            $pbTerm->greaterThanOrEqualTo('awarded_at', $range['from']->getDatabaseDate());
            if ($range['to']) {
                $pbTerm->lessThanOrEqualTo('awarded_at', $range['to']->getDatabaseDate());
            }
            $pbTerms->all($pbTerm);
        }
        if (!empty($ranges)) {
            $pb->any($pbTerms);
        }
        // end terms...

        $query  = $this->getSelectString() . ' JOIN ' . $this->getJoinSql('badge', '', 'b');
        $wheres = $pb->getWhere();
        if (count($wheres) > 0) {
            $query .= ' WHERE (' . implode(') AND (', $wheres) . ')';
        }
        $query     .= ' ORDER BY badge_user.awarded_at DESC ';
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($pb->getParams());

        return $this->hydrateSelect($statement);
    }


    /**
     * If the year's name contains only an integer, presume the user wanted
     * to prepend "Year " prefix given in front of it, because this is how it will
     * manifest in a UI somewhere.
     *
     * @param string|null $yearName
     * @param string      $yearPrefix
     * @return string
     */
    private function getLongName(?string $yearName, string $yearPrefix): string
    {
        if (preg_match('/^[0-9]+$/', $yearName)) {
            return sprintf("%s %s", $yearPrefix, $yearName);
        }

        return $yearName;
    }


    /**
     * Find a list of people who have received $badge at least once
     *
     * $roleType is one of the student/staff/parent/guest consts in Role
     *
     * @param string      $keyword
     * @param Badge\Model $badge
     * @param string      $awardStatus
     * @param string      $roleType
     * @param string      $yearPrefix
     * @return array
     */
    public function findRecipients(string $keyword, Badge\Model $badge, string $awardStatus, string $roleType, string $yearPrefix): array
    {
        /* No keyword provided; nothing to search
         */
        if (strlen($keyword) < self::MIN_CHARACTER_LIMIT) {
            return [];
        }

        /* Award status must be one of the prescribed enums in Award\Model
         */
        if (!\in_array($awardStatus, [Award\Model::AWARD_STATUS_ACHIEVEMENT, Award\Model::AWARD_STATUS_GOAL], true)) {
            return [];
        }

        $pb = $this->getPredicateBuilder()
            ->like('user.firstname', "%{$keyword}%")
            ->like('user.preferred_name', "%{$keyword}%")
            ->like('user.lastname', "%{$keyword}%")
            ->like('user.username', "%{$keyword}%")
            ->like('user.synergy_id', "%{$keyword}%");

        $where = implode(' OR ', $pb->getWhere());

        $query = "
            SELECT DISTINCT
                `user`.id,
                `user`.title,
                `user`.firstname,
                `user`.preferred_name,
                `user`.lastname,
                lyl.name AS year,
                `groups`.name AS role,
                `user`.synergy_id
            FROM `user`
            INNER JOIN `groups` ON `user`.group_id = `groups`.id
            INNER JOIN badge_user ON `user`.id = badge_user.recipient_id
            INNER JOIN badge ON badge.id = badge_user.badge_id
            LEFT JOIN lu_year_level lyl ON `user`.year_id = lyl.id
            WHERE `groups`.`group_type` = :roleType
            AND badge_user.badge_id = :badgeId
            AND badge_user.status = :status
            AND badge_user.deleted_at IS NULL
            AND badge.deleted_at IS NULL /* This excludes non-soft-deleted badge_user rows linked to soft-deleted badge rows. */
            AND ({$where})
        ";

        $params = $pb->getParams();

        $params['badgeId'] = $badge->getId();
        $params['status'] = $awardStatus;
        $params['roleType'] = $roleType;

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        $users = [];

        $formatter = (new User\Formatter\Factory())->buildFromConfig($this->getConfigLoader());
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* Manufacture a string to present to the user
             */
            $fullName = $formatter->formatArray($row);
            $person = "{$fullName} - ";

            if (isset($row['year'])) {
                $person .= $this->getLongName($row['year'], $yearPrefix) . ', ';
            }

            $person .= $row['role'];

            if ($row['synergy_id']) {
                $person .= " ({$row['synergy_id']})";
            }

            $users[] = [
                'name' => $person,
                'id' => $row['id'],
                'first_name' => $row['firstname'],
                'last_name' => $row['lastname'],
                'role' => $row['role'],
                'external_id' => $row['synergy_id']
            ];
        }

        return $users;
    }


    /**
     * Find the most recent awardings of $badge across all users in $userIds
     *
     * Intentionally avoid hydration as there's no point to doing all that
     * when we're just updating a scalar
     *
     * @param array       $userIds
     * @param Badge\Model $badge
     * @param string      $status
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function deleteMostRecentlyAwarded(array $userIds, Badge\Model $badge, string $status): void
    {

        /* Find a list of the most recent instances of $badge given
         * to the selected users and update their deleted_at via join
         * and a groupwise maximum
         *
         * https://stackoverflow.com/questions/15209414/how-to-do-3-table-join-in-update-query
         */
        $query = "
            SELECT bu1.id FROM badge_user bu1
            LEFT JOIN badge_user bu2
                ON bu1.recipient_id = bu2.recipient_id
                AND bu1.badge_id = bu2.badge_id
                AND bu1.awarded_at < bu2.awarded_at
                AND bu1.status = :status
                AND bu1.deleted_at IS NULL
                AND bu2.deleted_at IS NULL
            WHERE bu1.badge_id = :badgeId
            AND bu1.status = :status
            AND bu1.deleted_at IS NULL
            AND bu1.recipient_id IN {$this->getPredicateBuilder()->getArrayPlaceholders($userIds, 'recipient_id')}
            AND bu2.id IS NULL
        ";

        $params = $this->getPredicateBuilder()->getArrayParameters($userIds, 'recipient_id');
        $params['badgeId'] = $badge->getId();
        $params['status'] = $status;

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        $awardIdsToDelete = $statement->fetchAll(\PDO::FETCH_COLUMN);

        /* If there aren't any records to delete, just leave; nothing to do
         *
         * It's not really supposed to happen, but it isn't catastrophic
         * It occurs when you manipulate the timeline from the Goals or Achievements tabs
         */
        if (!\count($awardIdsToDelete)) {
            /* A Goal might prevent you from withdrawing an Achievement, for example
             */
            throw new \RuntimeException('No badges to withdraw');
        }

        /* Deleting awards using mapper, so that awarded notification will be retracted.
         */
        $this->deleteByColumn('id', $awardIdsToDelete);

        /* When an achievement is withdrawn, reset any goals to their unreached state.
         * A goal that was reached is associated with an achievement via the award_id column,
         * but if that achievement is withdrawn, then its goal should become reachable again.
         */
        if ($status === Award\Model::AWARD_STATUS_ACHIEVEMENT) {
            // Unset award_id
            $query  = "UPDATE badge_user SET award_id = NULL
                        WHERE award_id
                        IN {$this->getPredicateBuilder()->getArrayPlaceholders($awardIdsToDelete, 'award_id')}";
            $params = $this->getPredicateBuilder()->getArrayParameters($awardIdsToDelete, 'award_id');
            $statement = $this->getDatabase()->prepare($query);
            $statement->execute($params);
        }
    }


    /**
     * Get a set of award histories for everyone in $userIds, indexed by $userId
     *
     * This creates a hash lookup so you can use this in iterations without incurring
     * a lot of db requests
     *
     * @param array       $userIds
     * @param Badge\Model $badge
     * @return array
     * @throws \UnexpectedValueException
     */
    public function findHistories(array $userIds, \Schoolbox\Learning\Badge\Model $badge): array
    {
        $history = $this->findByColumnList([
            'recipient_id' => $userIds,
            'badge_id' => $badge->getId()
        ], [
            'order' => [
                'awarded_at' => 'desc'
            ]
        ]);

        /* Index histories by user id by initialising a new GenericCollection for each user
         *
         * A previous method using array_fill_keys initialised one GenericCollection that
         * was inadvertently shared among all users rather than initialising a new one for
         * each user as intended (object pass by reference, sigh)
         */
        $stack = [];

        foreach ($userIds as $userId) {
            $stack[$userId] = new GenericCollection(Model::class);
        }

        /* Stack up each person's history
         */
        foreach ($history as $item) {
            $stack[$item->getRecipient()->getId()]->add($item);
        }

        return $stack;
    }
}
