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
        $urlOverrides   = $this->getUrlOverrides();
        if (is_iterable($urlOverrides)) {
            foreach ($urlOverrides as $urlOverride) {
                $key                  = $urlOverride->getKey();
                $userSelections[$key] = $this->createSelection($key, true);
            }
        }
        return $userSelections;
    }


    /**
     * Add a list of previously selected folders to the filter's context.
     *
     * @return array|Filter\Selection\Model[]
     */
    public function toContext()
    {
        $context                 = parent::toContext();
        $userSelectionsSelectize = [];
        $userSelections          = $context['userSelections'] ?? [];
        if (is_iterable($userSelections)) {
            foreach ($userSelections as $userSelection) {
                if ($userSelection instanceof Filter\Selection\Model) {
                    $userSelectionsSelectize[] = [
                        'id'   => (int)$userSelection->getKey(),
                        'name' => 'Folder #' . $userSelection->getKey(), // TODO this ought to be a folder name
                    ];
                }
            }
        }
        $context['userSelectionsSelectize'] = $userSelectionsSelectize;
        return $context;
    }
}
