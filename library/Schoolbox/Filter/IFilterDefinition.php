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
     * Returns the twig template to render this filter's available options
     *
     * Intended to be called in Controller
     *
     * @return string
     */
    public function getOptionsTemplate();


    /**
     * Returns the twig template to render this filters roleType default selections
     *
     * Intended to be called in Controller
     *
     * @return string
     */
    public function getDefaultsTemplate();


    /**
     * Returns the twig template to render this filter's panel component into
     *
     * @return string
     */
    public function getPanelComponentTemplate();


    /**
     * Get all possible filter options, grouped by option.key, then indexed by option.page
     *
     * Options may be pulled from:
     * * DB,
     * * Hard-coded, or
     * * Both
     *
     * Must ensure inner collections are sorted the same as getPages()
     *
     * @param null|string $pageFilter If supplied, get options just for this page, otherwise get options for all available pages
     * @return Collection|GenericCollection[]|FilterOption\Model[][]
     */
    public function getOptions($pageFilter = null);


    /**
     * Returns sets of available abstract options, grouped by option.page, then indexed by option.key.
     * Each Option contains a set of Defaults, indexed by roleType
     *
     * @param null|string $pageFilter     If supplied, get defaults just for this page
     * @param null|string $roleTypeFilter If supplied, get defaults just for this roleType
     * @return Collection|GenericCollection[]|FilterOption\Model[][]
     */
    public function getAvailableOptions($pageFilter = null, $roleTypeFilter = null);


    /**
     * Get the key that this filter uses when building URL GET param lists
     *
     * eg /learning/markbook/151?f=43,176&tc=2015-2
     * * Folders 43 + 176
     * * Term 2015-2
     *
     * The value must be short and unique to enable lots of data in a GET url
     *
     * @return string
     */
    public static function getSlug();

    /**
     * Return an ordered list of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public static function getPages();


    /**
     * Return the set of possible abstract keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys();


    /**
     * Convert an abstract key into a label suitable for rendering in admin pages
     *
     * eg: 'all' => 'All'
     *     '1'   => 'Something that represents #1'
     *     '2'   => '2016-1 (current)'
     *
     * @param string $key
     * @return string
     * @throws \InvalidArgumentException If key is recognised as invalid
     */
    public function getLabel($key);

    /**
     * If the admin has not configured this option, should it be available in filter panels?
     *
     * By default, all possible options will be available on all pages. (ie just return true)
     *
     * @param string $customAbstractKey
     * @param string $page
     * @return bool
     */
    public function getInitialAvailability($customAbstractKey, $page);
}
