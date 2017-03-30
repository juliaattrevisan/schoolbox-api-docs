<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter\Definition\IFilter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Filter\Model as Filter;

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
     * @var GenericCollection|IFilter[]
     */
    private $filters;



    /**
     * @param User $user The viewing user.
     * @param string $page The page the panel is being rendered into
     * @param string $route The route to send the filter selections to
     * @param GenericCollection|IFilter[] $filters The set of filters to display in this panel instance
     */
    public function __construct(User $user, $page, $route, GenericCollection $filters)
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

    public function getFilters()
    {
        return $this->filters;
    }


    /**
     * Loads filter selections for the current user.
     * If none found, fall back to configured user.role.type defaults.
     */
    public function loadUserSelections()
    {
        foreach($this->filters as $filter){
            // Get list of defaults. For each, if user has selection, retain it. If user does not, create one for user with role default value
        }
    }


    /**
     * Returns a data structure ready to pump into twig
     * @return mixed[]
     */
    public function toContext()
    {
        return [
            'term' => [
                'id' => 1,
                'class' => '/defin/file/term',
                'options' => [
                    1 => [
                        'page' => 'pending',
                        'key' => 'current',
                        'selectable' => true,
                        'selections' => [
                            1 => [
                                'id' => 1,
                                'user' => 230,
                                'selected' => true,
                            ]
                        ]
                    ],
                    2 => [
                        'page' => 'pending',
                        'key' => 'previous',
                        'selectable' => true,
                        'selections' => [
                            1 => [
                                'id' => 1,
                                'user' => 230,
                                'selected' => false,
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
