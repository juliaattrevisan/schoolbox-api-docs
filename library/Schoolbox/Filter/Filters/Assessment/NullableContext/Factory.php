<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;

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
