<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\Component as BaseComponent;
use Schoolbox\GenericCollection;


class Component extends BaseComponent
{
    /**
     * This component has no role selections.
     *
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        return $this->getFilterSelectionMapper()->createCollection();
    }


    /**
     * @param GenericCollection $roleSelections
     * @return Filter\Selection\Model[]|GenericCollection
     */
    protected function buildUserSelections(GenericCollection $roleSelections)
    {
        $userSelections = $roleSelections->emptyClone();
        foreach ($this->getUrlOverrides() as $urlOverride) {
            // Find the selected folder
        }
        return $userSelections;
    }
}
