<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * @todo
 */
class Definition extends AbstractFilterDefinition
{

    /**
     * @var GenericCollection|Folder[]
     */
    private $classes;

    /**
     * @var GenericCollection|Folder[]
     */
    private $groups;


    public function __construct(
        Filter\Model $filter,
        Option\Mapper $filterOptionMapper,
        Defaults\Mapper $filterDefaultMapper,
        GenericCollection $classes,
        GenericCollection $groups
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->classes = $classes;
        $this->groups  = $groups;
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // TODO: Implement insertDynamicOptions() method.
    }


    /**
     * There may be existing availability settings saved to the DB that refer
     * to (most likely dynamic) items that no longer exist.
     *
     * Eg the admin may have configured that the Term 2017 should be available, but
     * it has subsequently been deleted from the system.
     *
     * Concrete classes can unset their relevant options here for similar reasons.
     *
     * @param GenericCollection|Option\Model[] $pageOptions
     * @return GenericCollection|Option\Model[]
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        // TODO: Implement unsetInvalidOptions() method.
    }


    /**
     * If not yet configured by the admin, should this filter option be selected by default
     *
     * By default, return false (never setting available option to be pre-selected)
     *
     * @param string $key
     * @param string $page
     * @param string $roleType
     * @return bool
     */
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // TODO: Implement getInitialSelectedState() method.
    }


    /**
     * Return the ordered, hard-codes list of custom keys for this filter.
     * These may represent concepts, eg 'all', 'past' etc, or concrete items, like '1' => Project, '2' => Quiz
     *
     * @return string[]
     */
    protected function getCustomKeys()
    {
        // TODO: Implement getCustomKeys() method.
    }


    /**
     * Each filter definition must know how to sort options
     *
     * @param GenericCollection|Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        // TODO: Implement sortFilterOptions() method.
    }


    /**
     * If the admin has not configured this option, should it be available in filter panels?
     *
     * By default, all possible options will be available on all pages. (ie just return true)
     *
     * @param string $customAbstractKey
     * @param string $page
     * @return bool
     */
    protected function getInitialAvailability($customAbstractKey, $page)
    {
        // TODO: Implement getInitialAvailability() method.
    }


    /**
     * Return an ordered list of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public function getPages()
    {
        // TODO: Implement getPages() method.
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys()
    {
        // TODO: Implement getAbstractKeys() method.
    }


    /**
     * Convert an key into a label suitable for rendering in admin pages
     *
     * eg: 'all' => 'All'
     *     '1'   => 'Something that represents #1'
     *     '2'   => '2016-1 (current)'
     *
     * @param string $key
     * @return string
     */
    public function getLabel($key)
    {
        // TODO: Implement getLabel() method.
    }
}
