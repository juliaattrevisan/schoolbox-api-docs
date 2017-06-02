<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Page;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Filter;
use Schoolbox\Role;

/**
 * Represents a filter instance on a page
 */
class Model
{

    // Pages with configurable filters
    // These are enums in DB, you will need to update column definition if you add to this list
    const PAGE_PENDING_ASSESSMENTS = 'pending';
    const PAGE_GRADES = 'grades';
    const PAGE_MARK_BOOK = 'markbook';
    const PAGE_LEARNING_ANALYTICS = 'analytics';
    const PAGE_DUE_WORK_CALENDAR = 'dueWorkCal';


    /**
     * @param string $page
     * @return string[]
     */
    public static function getRoleTypesForPage($page)
    {
        return self::getPageRoleTypes()[$page];
    }


    /**
     * Before the admin has configured it, should a filter on a page be enabled?
     *
     * @param Filter\Model $filter
     * @param string       $page
     * @return bool
     */
    public static function getEnabledInitialState(Filter\Model $filter, $page)
    {
        // For now, all available filters are enabled
        return true;
    }


    /**
     * Before the admin has configured it, should a filter on a page be user-configurable?
     *
     * @param Filter\Model $filter
     * @param string       $page
     * @return bool
     */
    public static function getUserConfigurableInitialState(Filter\Model $filter, $page)
    {
        // For now, if enabled, it is user-configurable also
        return static::getEnabledInitialState($filter, $page);
    }


    /**
     * Returns a list of roleTypes that can view each page
     *
     * @return string[][]
     */
    public static function getPageRoleTypes()
    {
        return [
            self::PAGE_PENDING_ASSESSMENTS => [
                Role::STAFF,
            ],
            self::PAGE_GRADES              => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
            ],
            self::PAGE_MARK_BOOK           => [
                Role::STAFF,
            ],
            self::PAGE_LEARNING_ANALYTICS  => [
                Role::STAFF,
            ],
            self::PAGE_DUE_WORK_CALENDAR   => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
            ],
        ];
    }


    use Id;

    /**
     * @var Filter\Model
     */
    private $filter;

    /**
     * Which page does this option relate to?
     *
     * @var string
     */
    private $page;


    /**
     * Is this page potentially filtered by this filter?
     *
     * @var bool
     */
    private $enabled;

    /**
     * Can the end-user see and configure this page filter?
     *
     * @var bool
     */
    private $userConfigurable;


    /**
     * @var GenericCollection|\Schoolbox\Filter\Page\Option\Model[]
     */
    private $options;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = new GenericCollection(Option\Model::class);
    }


    /**
     * How may options are available?
     *
     * @return int
     */
    public function getCountAvailableOptions()
    {
        $count = 0;
        foreach ($this->options as $option) {
            if ($option->getAvailable()) {
                $count++;
            }
        }
        return $count;
    }

    /* Getters / Setters
     */


    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * @param Filter\Model $filter
     * @return $this
     */
    public function setFilter(Filter\Model $filter)
    {
        $this->filter = $filter;
        return $this;
    }


    /**
     * @return string
     */
    public function getPage()
    {
        return $this->page;
    }


    /**
     * @param string $page
     * @return $this
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }


    /**
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }


    /**
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }


    /**
     * @return bool
     */
    public function getUserConfigurable()
    {
        return $this->userConfigurable;
    }


    /**
     * @param bool $userConfigurable
     * @return $this
     */
    public function setUserConfigurable($userConfigurable)
    {
        $this->userConfigurable = $userConfigurable;
        return $this;
    }


    /**
     * @return GenericCollection|\Schoolbox\Filter\Page\Option\Model[]
     */
    public function getOptions()
    {
        return $this->options;
    }


    /**
     * @param GenericCollection|\Schoolbox\Filter\Page\Option\Model[] $options
     * @return $this
     * @throws \UnexpectedValueException If Collection of wrong type
     */
    public function setOptions(GenericCollection $options)
    {
        $this->options->replaceWith($options);
        return $this;
    }

}
