<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\Name;

use Schoolbox\Filter;
use Schoolbox\Filter\BaseComponent;

/**
 * Component for rendering the name filter panel.
 */
class Component extends BaseComponent
{
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

        $searchQueryParts = [];
        foreach (($this->getUrlOverrides() ?? []) as $override) {
            $searchQueryParts[] = $override->getKey();
        }
        $context['searchQuery'] = implode(' ', $searchQueryParts);

        return $context;
    }
}
