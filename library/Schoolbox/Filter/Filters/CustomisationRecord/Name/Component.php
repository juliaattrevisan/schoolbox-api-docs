<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\Name;

use Schoolbox\Filter;
use Schoolbox\Filter\BaseComponent;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Component for rendering the name filter panel.
 */
class Component extends BaseComponent
{
    /**
     * Constructor
     *
     * @param Filter\IDefinition $definition
     * @param User               $user
     * @param Filter\Page\Model  $pageFilter
     * @param GenericCollection|null  $userSelections
     * @param GenericCollection|null  $urlOverrides
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param UserMapper              $userMapper
     * @param RosettaStone            $l10n
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        private UserMapper $userMapper,
        private RosettaStone $l10n,
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
     * @return array
     */
    public function toContext(): array
    {
        $context = parent::toContext();

        $searchQueryParts = [];
        foreach (($this->getUrlOverrides() ?? []) as $override) {
            $searchQueryParts[] = $override->getKey();
        }

        $users = $this->userMapper->findByColumn('id', $searchQueryParts);
        $context['userSelectionIds'] = $users->map(function (User $user) {
            $name = $user->getFullName() . " - ";

            /* Appending year level if exists with terminology
             */
            if ($user->getYear()) {
                $name .= $user->getYear()->getLongName(
                    $this->l10n->translate('year', false, true)
                ) . ", ";
            };

            /* Appending campus if exists, support multiple campuses
             */
            if ($user->getCampusCollection()) {
                $campusNames = $user->getCampusCollection()->map(function ($campus) {
                    return $campus->getName() . " Student";
                })->getAll();

                $name .= implode(", ", $campusNames);
            }

            /* Appending external ID if exists
             */
            if ($user->getExternalId()) {
                $name .= " (" . $user->getExternalId() . ")";
            }

            return [
                'id' => $user->getId(),
                'name' => $name
            ];
        })->getAll();

        return $context;
    }
}
