<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use TypeError;

/**
 * These filters may be used on pages that fetch assessments that reside on either:
 * * a single folder, which is part of the URL/route
 * * multiple folders, which are GET params/filter options
 *
 * In both cases, route args and request params are injected into this factory's buildComponent() method.
 */
abstract class Factory extends Filter\AbstractFactory
{

    /**
     * Finds a returns the first folder model from an array of route args
     *
     * @param array $routeArgs
     * @return Folder\Model
     * @throws TypeError If no Folder\Model found
     */
    protected function findFolderModel(array $routeArgs): Folder\Model
    {
        foreach ($routeArgs as $routeArg) {
            if ($routeArg instanceof Folder\Model) {
                return $routeArg;
            }
        }
        return null;
    }


    /**
     * Do the overrides indicate any folders are being inserted into the folder filter option list?
     *
     * If we insert a folder into a folder filter option list, we need to insert any corresponding items
     * into the nullable context filters so that assessments in those nullable contexts on the inserted
     * folder can be matched by the nullable context filters.
     *
     * @param GenericCollection|GenericCollection[]|Filter\Selection\Model[][] $overrides Indexed by filter.id, selection.key
     * @return int[]
     */
    protected function parseFolderIds(GenericCollection $overrides): array
    {
        $folderFilters = Filter\Mapper::getInstance()->findByClass([
            Filter\Filters\Assessment\Folder\TeacherInvolved\Definition::class,
            Filter\Filters\Assessment\Folder\StudentInteractions\Definition::class,
        ]);

        $folderIds = [];

        foreach ($folderFilters as $folderFilter) {
            $folderOverrides = $overrides[$folderFilter->getId()];
            if ($folderOverrides) {
                /** @var Filter\Selection\Model $folderOverride */
                foreach ($folderOverrides as $folderOverride) {
                    if ($folderOverride->getSelected()) {
                        $folderIds[$folderOverride->getKey()] = $folderOverride->getKey();
                    }
                }
            }
        }
        return $folderIds;
    }
}
