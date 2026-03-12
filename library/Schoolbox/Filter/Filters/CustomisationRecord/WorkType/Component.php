<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\WorkType;

use Schoolbox\Filter;
use Schoolbox\Filter\IDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Work Type filter component for customisation records
 */
class Component extends Filter\BaseComponent
{
    public function __construct(
        IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        private GenericCollection $workTypes,
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
        $roleType = $this->getUser()->getRole()->getType();

        foreach ($this->workTypes as $workType) {
            $key = (string)$workType->getId();
            $isSelected = $abstractOptions[$key]?->getDefaults()[$roleType]?->isSelected() ?? false;
            $availableSelections[$key] = $this->createSelection($key, $isSelected);
        }

        return $availableSelections;
    }
}
