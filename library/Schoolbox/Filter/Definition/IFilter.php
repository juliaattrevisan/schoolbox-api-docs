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
use Schoolbox\Filter\Model as Filter;

/**
 * All filter definitions must provide an implementation that adheres to this interface.
 *
 * Responsibilities:
 * *
 */
interface IFilter
{

    /**
     * @return Filter
     */
    public function getFilter();

    /**
     * Returns the twig template to render this filters options
     *
     * Intended to be called in Controller
     *
     * @return string
     */
    public function getOptionsTemplate();

    public function getRoleDefaultsTemplate();

    /**
     * Returns the twig template to render into the filter panel
     *
     * Intended to be called in twig includes
     *
     * @return string
     */
    public function getPanelTemplate();



    /**
     * Returns a set of data to inject into this filter's template
     *
     * Intended to be called in twig includes
     *
     * @param string $page
     * @param User $user
     * @return mixed[]
     */
    public function toContext($page, User $user);


    /**
     * Get all available filter options, grouped by option.key, then indexed by option.page
     *
     * Options may be pulled from:
     * * DB,
     * * Hard-coded, or
     * * Both
     *
     * Must ensure inner collections are sorted the same as getPages()
     *
     * @return FilterOption[][]
     */
    public function getOptions();


    /**
     * Returns sets of options, grouped by option.page, then indexed by option.key.
     * Each Option contains a set of Defaults, indexed by roleType
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getOptionDefaults();


    /**
     * Return the set of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public function getPages();




    // ^^ above are confirmed clean  --- VV below are yet to be confirmed //










    /**
     * Is the roleType valid for this option? Used for sanitising input
     *
     * @param string $roleType
     * @param FilterOption $option
     * @return bool
     */
    public function isValidRoleType($roleType, FilterOption $option);



    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getKeys();





}
