<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext;

use Schoolbox\Collection;
use Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition;
use Schoolbox\GenericCollection;
use Schoolbox\Filter;
use Schoolbox\User;


/**
 * Specialised logic to deal with the 'KEY_NONE' semantics.
 *
 * There is a coupling between the availability of options in the folder and nullable-context filters.
 * The folder filter is equal to the user’s involved classes and/or groups. The nullable-context
 * filters are derived from the user’s involved classes and groups.
 *
 * Consider this scenario:
 * * User is involved with two folders, being class FC and group FG.
 * * User has imported units UC and UG to folders FC and FG respectively.
 * * User has an assessment A imported from UC to FC.
 * * Admin configures folder filter to just show classes, and the unitImportSpec (unit) filter
 *   to just show units imported to the user’s groups.
 * * User will see a panel with folder FC and unit UG.
 * * User selects folder FC, and does not select any options in the unit filter.
 *
 * In this case, the user expectation is that the unit filter is not being used (as they have selected no options), and
 * may expect to see assessment A because it lives in FC. They will not see assessment A because it was imported
 * from a unit (UC) that the admin has configured not to be available.
 *
 * We could detect this scenario, and set the unit filter to null, but what would happen if all available
 * units were selected? In this case we normally present all available option being selected as not applying the filter. But the unit
 * is not in the list of available options, so should we include it or not?
 *
 * Mathematically, the user should only be able to see assessments if they match the available option. In this case, since
 * the admin has configure the unit filter to be enabled, but not to allow assessments in units imported to groups, the
 * user should not be able to see assessment A.
 *
 * The real problem is that the admin has configured the filters to be potentially confusing.
 *
 * Outcome:
 * * Consider adding JS to enforce business rule:
 * * * If nullable context-filter is enabled and user-configurable, its available options must correspond to the folder filter.
 *     Eg do not permit admin to configure folder groups to not be available, but units imported to groups to be available.
 */
class KeyConverter extends Filter\AbstractKeyConverter
{
    /**
     * All IDs known to the system (regardless of whether they are available for
     * selection for current user)
     *
     * We use IDs instead of Models as too slow for no value
     *
     * @var Collection|int[]
     */
    private $ids;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $ids
     */
    public function __construct(Filter\Model $filter, Collection $ids)
    {
        parent::__construct($filter);
        $this->ids = $ids->shallowClone();
    }


    /**
     * The returned Collection contains ids and/or 'KEY_NONE' => null
     *
     * @inheritdoc
     * @todo This is almost identical to parent. Need to abstract out common bits. See inline comments for different logic.
     */
    public function getSelectedItems(GenericCollection $availableSelections, User $user, string $page): ?Collection
    {
        /** @var Collection|int[] $allItems */
        $allItems       = $this->getAllPossibleItems();
        $availableItems = $allItems->emptyClone();
        $selectedItems  = $allItems->emptyClone();

        // No available selections?
        if (0 === $availableSelections->count()) {
            // Short-circuit and return empty collection
            return $selectedItems;
        }

        // Build the available and selected item collections
        foreach ($availableSelections as $selection) {
            $key  = $selection->getKey();
            $item = $this->getItem($key, $user, $page);
            $availableItems->offsetSet($key, $item);
            if ($selection->getSelected()) {
                $selectedItems->offsetSet($key, $item);
            }
        }

        // todo this is different to parent logic
        // Was either just the '(none)' option selected, or the only option available is '(none)' ?
        if ((1 === $selectedItems->count() && $selectedItems->keyExists(Definition::KEY_NONE)) ||
            (1 === $availableItems->count() && $availableItems->keyExists(Definition::KEY_NONE))) {
            // return an empty set. ie assessments not in this context
            return $allItems->emptyClone();
        }

        // todo this is different to parent logic
        // All concrete items plus the '(none)' option
        $countAll       = $allItems->count() + 1;
        $countSelected  = $selectedItems->count();
        $countAvailable = $availableItems->count();

        // Are are all possible options available, and none or all were selected ?
        if ($countAll === $countAvailable && (0 === $countSelected || $countAvailable === $countSelected)) {
            // Return NULL - so the DB generator can forgo the filter entirely
            return null;
        }

        // Are a subset of possible options available, and none of the subset were selected ?
        if($countAll !== $countAvailable && 0 === $countSelected){
            // Nothing selected is the same as all available options being selected.
            return $availableItems;
        }

        // Either not all possible items were available for selection, or a subset of items was selected.
        return $selectedItems;
    }



    /**
     * Given a concrete key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @inheritdoc
     * @return null|int
     */
    public function getItem(string $key, User $user, string $page): ?int
    {
        if (Definition::KEY_NONE === $key) {
            return null;
        }
        $this->assertValidKey($key, $user, $page);
        return $this->ids[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->ids->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (Definition::KEY_NONE === $key) {
            return;
        }
        if (!$this->ids->offsetExists($key)) {
            throw new \InvalidArgumentException("No ID found for key: $key");
        }
    }
}
