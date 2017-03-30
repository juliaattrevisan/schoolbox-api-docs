<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Option;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Filter\Model as Filter;
use Schoolbox\Role;
use Schoolbox\Filter\Option\Defaults\Model as FilterDefault;
use Schoolbox\Filter\Option\Defaults\Mapper as FilterDefaultMapper;

class Model
{
    // Pages with configurable filters
    // These are enums in DB, you will need to update column definition if you add to this list
    const PAGE_PENDING_ASSESSMENTS = 'pending';
    const PAGE_GRADES              = 'grades';
    const PAGE_MARK_BOOK           = 'markbook';
    const PAGE_LEARNING_ANALYTICS  = 'analytics';
    const PAGE_DUE_WORK_CALENDAR   = 'dueWorkCal';


    /**
     * Return the enum set
     * @return string[]
     */
    public static function getPages()
    {
        return [
            self::PAGE_PENDING_ASSESSMENTS,
            self::PAGE_GRADES,
            self::PAGE_MARK_BOOK,
            self::PAGE_LEARNING_ANALYTICS,
            self::PAGE_DUE_WORK_CALENDAR,
        ];
    }


    /**
     * @param string $page
     * @return string[]
     * @todo move this and make more efficient
     */
    public static function getRoleTypesForPage($page)
    {
        return self::getPageRoleTypes()[$page];
    }


    /**
     * Returns a list of roleTypes that can view each page
     * @return string[][]
     * @todo - this does not belong here. FilterOption should not be coupled to Role.
     */
    public static function getPageRoleTypes()
    {
        return [
            self::PAGE_PENDING_ASSESSMENTS => [
                Role::STAFF,
            ],
            self::PAGE_GRADES => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
            ],
            self::PAGE_MARK_BOOK => [
                Role::STAFF,
            ],
            self::PAGE_LEARNING_ANALYTICS => [
                Role::STAFF,
            ],
            self::PAGE_DUE_WORK_CALENDAR => [
                Role::STAFF,
                Role::PARENT,
                Role::STUDENT,
            ],
        ];
    }

    use Id;

    /**
     * @var Filter
     */
    private $filter;

    /**
     * Which page does this option relate to?
     * @var string
     */
    private $page;

    /**
     * The key may be an ID of a model, eg a Term, or some other dynamic identifier, eg 'current' term.
     * It is up to the concrete filter definitions to apply semantics to this value.
     * @var string
     */
    private $key;

    /**
     * Should this option be selectable in the filter?
     * @var bool
     */
    private $selectable;

    /**
     * The roleType-specific defaults configured by the admin for this option
     * @var GenericCollection|FilterDefault[]
     */
    private $defaults;


    public function __construct()
    {
        $this->defaults = FilterDefaultMapper::getInstance()->createCollection();
    }


    /**
     * @return Filter
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * @param Filter $filter
     * @return static
     */
    public function setFilter(Filter $filter)
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
     * @return static
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }


    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @param string $key
     * @return static
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }


    /**
     * @return bool
     */
    public function getSelectable()
    {
        return $this->selectable;
    }


    /**
     * @param bool $selectable
     * @return static
     */
    public function setSelectable($selectable)
    {
        $this->selectable = $selectable;
        return $this;
    }


    /**
     * @return GenericCollection|FilterDefault[]
     */
    public function getDefaults()
    {
        return $this->defaults;
    }


    /**
     * @param FilterDefault[]|GenericCollection $defaults
     * @return static
     */
    public function setDefaults(GenericCollection $defaults)
    {
        $this->defaults->replaceWith($defaults);
        return $this;
    }

}
