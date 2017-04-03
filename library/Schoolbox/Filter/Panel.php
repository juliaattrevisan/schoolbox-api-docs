<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;

/**
 * A filter panel holds one or more filters.
 *
 * Each filter renders a sub-panel, containing:
 * * a title for the filter
 * * filter options able to be selected by the user
 */
class Panel
{
    /**
     * @var string
     */
    private $route;

    /**
     * @var GenericCollection|IFilterComponent[]
     */
    private $filterComponents;



    /**
     * @param string                                $route   The route to send the filter selections to
     * @param GenericCollection|IFilterComponent[] $filterComponents The set of filters to display in this panel instance
     */
    public function __construct($route, GenericCollection $filterComponents)
    {
        $this->route = $route;
        $this->filterComponents = $filterComponents;
    }

    public function getTemplate()
    {
        return '@filters/panel.twig';
    }

    public function getRoute()
    {
        return $this->route;
    }


    public function getFilterComponents()
    {
        return $this->filterComponents;
    }
}
