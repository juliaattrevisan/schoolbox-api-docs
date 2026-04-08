<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\AdjustmentCategory;

use Schoolbox\Filter;
use Schoolbox\Filter\BaseComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as DpMapper;
use Schoolbox\Admin\DifferentiationProfiles\Model as DpModel;

/**
 * Component for rendering the adjustment category filter panel.
 */
class Component extends BaseComponent
{
    public function __construct(
        IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        private DpMapper $differentiationProfileMapper
    ) {
        parent::__construct(
            $definition,
            $user,
            $pageFilter,
            $userSelections,
            $urlOverrides,
            $filterSelectionMapper,
        );
    }

    /**
     * This component's context contains the URL overrides (which is what was
     * input into this component's text input), concatenated into a single
     * string.
     *
     * @return array|Filter\Selection\Model[]|string[]
     */
    public function toContext(): array
    {
        $context = parent::toContext();

        try {
            /** @var DpModel $adjustmentCategoryObj */
            $adjustmentCategoryObj = $this->differentiationProfileMapper
                ->findOneByColumn('name', 'adjustment_category');
            $context['terminology'] =
                ((string)$adjustmentCategoryObj->getTerminology()) ?:
                    $adjustmentCategoryObj->getTitle();
        } catch (\Exception $e) {
            /* For any reasons that it fails to retrieve the config
             */
            $context['terminology'] = 'Adjustment Category';
        }

        return $context;
    }
}
