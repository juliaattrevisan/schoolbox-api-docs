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
     */
    public function updateSelections(User $user, $page, Collection $selectionsPerFilter)
    {
        // Delete user's current selections for this page
        $this->deleteByColumnList([
            'user_id' => $user->getId(),
            'page'    => $page,
        ]);

        // Save User's current selections
        $selectionsToSave = $this->createCollection();
        foreach ($selectionsPerFilter as $filterId => $filterSelections) {
            /** @var Model[] $filterSelections */
            foreach ($filterSelections as $key => $selection) {
                $selectionsToSave->add($selection);
            }
        }
        return $this->save($selectionsToSave);
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
