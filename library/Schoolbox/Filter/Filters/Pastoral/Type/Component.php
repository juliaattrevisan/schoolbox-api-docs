<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Type;

use Schoolbox\Filter;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Component definition for pastoral type/subtype filter.
 *
 * This component contains a list of pastoral types, in addition to what the
 * standard component contains.
 */
class Component extends Filter\BaseComponent
{
    /**
     * Constructor
     *
     * @param IDefinition            $definition
     * @param User                   $user
     * @param Page\Model             $pageFilter
     * @param GenericCollection|null $userSelections
     * @param GenericCollection|null $urlOverrides
     * @param Selection\Mapper       $filterSelectionMapper
     * @param GenericCollection      $types
     */
    public function __construct(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Selection\Mapper $filterSelectionMapper,
        private GenericCollection $types,
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $urlOverrides, $filterSelectionMapper);
    }

    /**
     * @inheritdoc
     */
    public function toContext()
    {
        return array_merge(
            parent::toContext(),
            ['types' => $this->types],
        );
    }
}
