<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter\Definition\IFilterDefinition;
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

    /**
     * @var string
     */
    private $route;

    /**
     * @var GenericCollection|IFilterDefinition[]
     */
    private $filters;



    /**
     * @param User $user The viewing user.
     * @param string $route The route to send the f
     * @param string[] $filterClasses The set of filters to display in this panel instance
     * @throws \InvalidArgumentException if class not found
     */
    public function __construct(User $user, $route, $filterClasses)
    {
        $this->user = $user;
        $this->route = $route;

        // Validate and load filter definitions
        $this->filters = new GenericCollection(IFilterDefinition::class);
        foreach($filterClasses as $filterClass){
            if(!class_exists($filterClass)){
                throw new \InvalidArgumentException('Class not found: ' . $filterClass);
            }
            $this->filters->add(new $filterClass());
        }
    }


    /**
     * Loads filter selections for the current user.
     * If none found, fall back to configured user.role.type defaults.
     */
    public function execute()
    {
        foreach($this->filters as $filter){
            $filter->buildFilterDefaults();
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
