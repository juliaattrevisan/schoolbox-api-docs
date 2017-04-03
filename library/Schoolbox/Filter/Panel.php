<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * A filter panel holds one or more filters.
 *
 * Each filter renders a sub-panel, containing:
 * * a title for the filter
 * * filter options able to be selected by the user
 */
class Panel
{
    /** @var User */
    private $user;

    /** @var string */
    private $page;

    /**
     * @var string
     */
    private $route;

    /**
     * @var GenericCollection|IFilterDefinition[]
     */
    private $filters;



    /**
     * @param string                                $route   The route to send the filter selections to
     * @param GenericCollection|IFilterDefinition[] $filters The set of filters to display in this panel instance
     */
    public function __construct($route, GenericCollection $filters)
    {
        $this->user = $user;
        $this->page = $page;
        $this->route = $route;
        $this->filters = $filters;
    }

    public function getTemplate()
    {
        return '@filters/panel.twig';
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function getRoute()
    {
        return $this->route;
    }


    public function getFilters()
    {
        return $this->filters;
    }
}
