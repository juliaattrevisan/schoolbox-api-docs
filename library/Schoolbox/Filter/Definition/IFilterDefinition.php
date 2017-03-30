<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


namespace Schoolbox\Filter\Definition;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\Filter\Option\Selection\Model as FilterSelection;
use Schoolbox\Filter\Option\Defaults\Model as FilterDefault;

/**
 * All filter types must provide an implementation that adheres to this interface.
 */
interface IFilterDefinition
{

    /**
     * @return int
     */
    public function getId();

    /**
     * Return all options available for this filter.
     *
     * Options may be pulled from:
     * * DB,
     * * Hard-coded, or
     * * Both
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function buildFilterOptions();


    /**
     * Indexed by filterOption.key, then filterOption.page
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getFilterOptionsByKeyThenPage();


    /**
     * Indexed by filterOption.page, filterOption.key, filterDefault.role
     * @return Collection|Collection[]|GenericCollection[][]|FilterDefault[][][]
     */
    public function buildFilterDefaults();


    /**
     * Indexed by filterOption.page, then filterOption.key
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getFilterOptionsByPageThenKey();

    /**
     * @param string $key
     * @return bool
     */
    public function isValidKey($key);

    /**
     * Is the roleType valid for this option? Used for sanitising input
     *
     * @param string $roleType
     * @param FilterOption $option
     * @return bool
     */
    public function isValidRoleType($roleType, FilterOption $option);


    /**
     * Is this option displayed in the filter?
     *
     * Options will always be displayed if the admin has explicitly selected them.
     * Otherwise it is up to the filter type. Eg, the term Filter defaults to not showing terms older than 4 years if admin has not explicitly selected them.
     *
     * @param FilterOption $filterOption
     * @return bool
     */
    public function isOptionAvailable(FilterOption $filterOption);

    /**
     * Return the set of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public function getPages();

    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getKeys();


    /**
     * Return the admin-configured default selections for specified role type.
     *
     * If not configured in admin, fall back to hard-coded sensible defaults.
     *
     * @param string $roleType
     * @return GenericCollection|FilterDefault[]
     */
    public function getDefaultsForRole($roleType);


    /**
     * @param User $user
     * @return GenericCollection|FilterSelection[]
     */
    public function getSelectionsForUser(User $user);


}
