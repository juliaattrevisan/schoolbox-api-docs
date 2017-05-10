<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;

/**
 * A filter panel holds one or more filter components
 *
 * Each filter component renders a sub-panel, containing:
 * * a title for the filter
 * * concrete filter options able to be selected by the user
 */
class Panel
{

    /**
     * @var string
     */
    private $route;

    /**
     * Indexed by filter Definition class
     *
     * @var GenericCollection|IFilterComponent[]
     */
    private $components;


    /**
     * @param string                               $route      The route to send the filter selections to
     * @param GenericCollection|IFilterComponent[] $components The set of filters to display in this panel instance
     */
    public function __construct($route, GenericCollection $components)
    {
        $this->route      = $route;
        $this->components = $components;
    }


    /**
     * @return string
     */
    public function getTemplate()
    {
        return '@filters/panel.twig';
    }


    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }


    /**
     *
     * @return IFilterComponent[]|GenericCollection
     */
    public function getComponents()
    {
        return $this->components;
    }
}
