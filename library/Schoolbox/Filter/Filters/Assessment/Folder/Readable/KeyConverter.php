<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\KeyConverter as BaseKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Key converter for the readable folder filter.
 *
 * Differs from the standard folder key converter only in that if no selections
 * are made, that counts as NULL (letting everything through) instead of empty
 * (letting nothing through).
 */
class KeyConverter extends BaseKeyConverter
{
    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * Constructor
     *
     * @param Filter\Model  $filter
     * @param Collection    $folderIds
     * @param Access        $access
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(
        Filter\Model $filter,
        Collection $folderIds,
        Access $access,
        Folder\Mapper $folderMapper
    ) {
        parent::__construct($filter, $folderIds, $access);
        $this->folderMapper = $folderMapper;
    }


    /**
     * @inheritDoc
     */
    public function getSelectedItems(GenericCollection $availableSelections, User $user, string $page): ?Collection
    {
        // If nothing is selected, the default selection is:
        // every folder within the options that are enabled for this user's
        // role type.
        if ($availableSelections->count() === 0) {
            // Build the list of user/other groups/classes
            // TODO consider moving this to another class, as Factory::buildComponent uses the same lookups
            $userClasses  = $this->folderMapper->findFolderIdsForUser($user, true, UserAccess::ACCESS_FULL + 1);
            $userGroups   = $this->folderMapper->findFolderIdsForUser($user, false);
            $otherClasses = $this->folderMapper->findIdsByPredicates(
                $this->folderMapper->getPredicateBuilder()
                                   ->notNull('cc.code')
                                   ->isNull('cc.deleted_at')
            );
            $otherClasses = array_diff($otherClasses, $userClasses, $userGroups);
            $otherGroups  = $this->folderMapper->findIdsByPredicates(
                $this->folderMapper->getPredicateBuilder()
                                   ->notNull('ug.cid')
            );
            $otherGroups  = array_diff($otherGroups, $otherClasses, $userClasses, $userGroups);

            // Start building the list of selected items
            // Only select folders which are available for this user's role type
            $selectedItems    = $this->getAllPossibleItems()->emptyClone();
            $pageModel        = $this->getFilter()->getPages()[$page];
            $availableOptions = [
                Definition::KEY_MY_CLASSES,
                Definition::KEY_MY_GROUPS,
                Definition::KEY_OTHER_CLASSES,
                Definition::KEY_OTHER_GROUPS,
            ];
            foreach ($availableOptions as $availableOption) {
                // Account for if no page option at all exists:
                // assume that it is available in this case.
                // (This happens if the settings for this filter have never been
                //  changed, because no options at all exist for this filter.)
                $pageOption = $pageModel->getOptions()[$availableOption] ?? null;
                if (!$pageOption || $pageOption->getAvailable()) {
                    switch ($availableOption) {
                        case Definition::KEY_MY_CLASSES:
                            $selectedItems = $selectedItems->mergeUnique(new Collection($userClasses));
                            break;
                        case Definition::KEY_MY_GROUPS:
                            $selectedItems = $selectedItems->mergeUnique(new Collection($userGroups));
                            break;
                        case Definition::KEY_OTHER_CLASSES:
                            $selectedItems = $selectedItems->mergeUnique(new Collection($otherClasses));
                            break;
                        case Definition::KEY_OTHER_GROUPS:
                            $selectedItems = $selectedItems->mergeUnique(new Collection($otherGroups));
                            break;
                    }
                }
            }
            return $selectedItems->indexByCallback(static function ($v) { return $v; });
        }
        return parent::getSelectedItems($availableSelections, $user, $page);
    }
}
