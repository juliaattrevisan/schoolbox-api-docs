<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\Name;

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
     * @return array
     */
    public function toContext()
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
