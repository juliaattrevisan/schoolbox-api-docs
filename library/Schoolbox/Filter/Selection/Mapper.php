<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Selection;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('filter_selection');
        $this->hasModel(Model::class);

        $this->joins('filter_id', Filter\Mapper::class, 'id', self::TYPE_MODEL, null, 'filter', 'filter');
        $this->joins('user_id', UserMapper::class, 'id', self::TYPE_MODEL, null, 'user', 'user');
        $this->uses(
            'page',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            'page',
            [
                Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
                Filter\Page\Model::PAGE_GRADES,
                Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
                Filter\Page\Model::PAGE_COURSE_MARK_BOOK,
                Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
                Filter\Page\Model::PAGE_LEARNING_ANALYTICS,
                Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
                Filter\Page\Model::PAGE_ASSESSMENT_CALENDAR,
                Filter\Page\Model::PAGE_BADGE_LIST,
                Filter\Page\Model::PAGE_AWARD_LIST,
                Filter\Page\Model::PAGE_CLASS_PASTORAL,
            ]
        );
        $this->uses('key', self::TYPE_STRING);
        $this->uses('selected', self::TYPE_BOOLEAN);
    }


    /**
     * Update the specified user's filter selections for the specified page
     * Returns the saved selections, grouped by filter.id, then indexed by selection.key
     *
     * @param User                                     $user
     * @param string                                   $page
     * @param Collection|GenericCollection[]|Model[][] $selectionsPerFilter Indexed by filter.id, selection.key
     * @return bool
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function updateSelections(User $user, $page, Collection $selectionsPerFilter)
    {
        // Build user's current selections
        $selectionsToSave = $this->createCollection();

        /** @var Model[] $filterSelections */
        foreach ($selectionsPerFilter as $filterId => $filterSelections) {
            foreach ($filterSelections as $key => $selection) {
                $selectionsToSave->add($selection);
            }
        }

        // Delete and save user's selections atomically
        return $this->database->transact(function () use ($user, $page, $selectionsToSave) {
            $this->deleteByColumnList([
                'user_id' => $user->getId(),
                'page'    => $page,
            ]);
            $this->save($selectionsToSave);
        });
    }


    /**
     * Deletes selections for page `$page`, for all users whose role is in
     * `$roleIds`.
     *
     * (Deleting a user's selected filters will cause their selections to reset
     *  back to the default values in `filter_default` for their roletype.)
     *
     * @param array  $roleIds
     * @param string $page
     */
    public function deleteSelectionsForRoleIds(array $roleIds, string $page): void
    {
        $builder = $this->getPredicateBuilder()
            ->in('u.group_id', $roleIds)
            ->equals('fs.page', $page);
        $query = $this->getDatabase()->prepare("
            DELETE fs FROM {$this->table} fs
            INNER JOIN {$this->getJoinSql('user', 'fs', 'u')}
            WHERE {$builder->getWhereIntersect()}
        ");
        $query->execute($builder->getParams());
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
