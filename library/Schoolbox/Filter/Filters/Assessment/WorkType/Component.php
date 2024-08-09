<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Schoolbox\Filter;
use Schoolbox\Filter\IDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class Component extends Filter\BaseComponent
{
    /**
     * @param IDefinition $definition
     * @param User $user
     * @param Filter\Page\Model $pageFilter
     * @param GenericCollection|null $userSelections a set of Filter\Selection\Model[]
     * @param GenericCollection|null $urlOverrides a set of Filter\Selection\Model[]
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param GenericCollection $workTypes
     * @throws \InvalidArgumentException
     */
    public function __construct(
        IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        private GenericCollection $workTypes
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $urlOverrides, $filterSelectionMapper);
    }


    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions): GenericCollection
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        foreach ($this->workTypes as $workType) {
            $key                       = (string)$workType->getId();
            $isSelected                = $abstractOptions[$key]->getDefaults()[$roleType]->isSelected();
            $availableSelections[$key] = $this->createSelection($key, $isSelected);
        }

        return $availableSelections;
    }
}
