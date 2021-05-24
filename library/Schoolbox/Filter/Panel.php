<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * Which filtered page this is. One of the Filter\Page\Model::PAGE_* constants
     *
     * @var string
     */
    private $page;

    /**
     * @var string
     */
    private $route;

    /**
     * Indexed by filter Definition class
     *
     * @var GenericCollection|IComponent[]
     */
    private $components;


    /**
     * @param string                         $page
     * @param string|null                    $route      The route to send the filter selections to.
     *                                                   If null, don't send anywhere.
     * @param GenericCollection|IComponent[] $components The set of filters to display in this panel instance
     */
    public function __construct(string $page, ?string $route, GenericCollection $components)
    {
        $this->page       = $page;
        $this->route      = $route;
        $this->components = $components;
    }


    /**
     * @return string
     */
    public function getPage(): string
    {
        return $this->page;
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
     * @return IComponent[]|GenericCollection
     */
    public function getComponents()
    {
        return $this->components;
    }
}
