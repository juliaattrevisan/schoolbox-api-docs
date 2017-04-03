<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\Filter;

/**
 * All filter definitions must provide an implementation that adheres to this interface.
 *
 * You should not inject mappers into the filter definitions. They should not do DB calls. The factory should perform
 * the calls and call the required setters for the definition for it to do its work.
 *
 * 3 Responsibilities
 * * Rendering available options and default role selections in the admin pages
 * * Rendering options in a filter panel on a filterable page
 * * Converting user option selections into entities when generating a filterable page's contents
 *
 * Each of these 3 purposes has a corresponding buildFor* method
 */
interface IFilterDefinition
{
    /**
     * @return Filter\Model
     */
    public function getFilter();

    /**
     * @return string
     */
    public function getAdminTemplate();

    /**
     * Returns the twig template to render this filters options
     *
     * Intended to be called in Controller
     *
     * @return string
     */
    public function getOptionsTemplate();

    /**
     * @return string
     */
    public function getDefaultsTemplate();


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
     * @return Collection|GenericCollection[]|FilterOption\Model[][]
     */
    public function getOptions();


    /**
     * Returns sets of options, grouped by option.page, then indexed by option.key.
     * Each Option contains a set of Defaults, indexed by roleType
     *
     * @return Collection|GenericCollection[]|FilterOption\Model[][]
     */
    public function getOptionDefaults();


    /**
     * Return the model or entity identified by this key
     *
     * @param string $key
     * @return mixed
     */
    public function getEntityByKey($key);


    /**
     * Return the set of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public function getPages();


    /**
     * Is the roleType valid for this option? Used for sanitising input
     *
     * @param string $roleType
     * @param FilterOption\Model $option
     * @return bool
     */
    public function isValidRoleType($roleType, FilterOption\Model $option);


    /**
     * Return the set of possible abstract keys for this filter's options
     *
     * @return string[]
     * @todo - consider splitting into 1-getOptionKeys() + 2-getSettingKeys()
     */
    public function getAbstractKeys();





}
