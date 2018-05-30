<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;

/**
 * All filter definitions must provide an implementation that adheres to this interface.
 *
 * Definitions should not do DB calls. They should just contain their specific logic.
 *
 * Their corresponding factory should insert all required dependencies and data.
 */
interface IDefinition
{

    /**
     * Return the name of the filter to expose in the UI
     *
     * @return string
     */
    public static function getName();


    /**
     * Is this filter mandatory for the specified page?
     *
     * If so, the admin will not be able to disable this filter from appearing on the specified page.
     *
     * No effect on user-configurability.
     *
     * @param string $page
     * @return bool
     */
    public static function isMandatory($page);


    /**
     * @return Model
     */
    public function getFilter();


    /**
     * Returns the twig template to render this filter's panel component into
     *
     * @return string
     */
    public static function getPanelComponentTemplate();


    /**
     * Removes any invalid items and inserts logically missing items.
     *
     * Initially, the system has no configured options. In this case we need to insert
     * initial state so that we can render in admin and in filter panels.
     *
     * Over time, we may get invalid options that require removed.
     * eg, if an option represents a Term that is deleted from the system
     *
     * @param Page\Model  $pageFilter
     * @param null|string $roleTypeFilter
     * @return GenericCollection|Page\Option\Model[]
     */
    public function normalisePageOptions(Page\Model $pageFilter, $roleTypeFilter = null);


    /**
     * Return an ordered list of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public static function getPages();


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
     * Prior to admin-configuration, should this filter be enable on the specified page?
     *
     * @param string $page
     * @return bool
     */
    public static function getEnabledInitialState($page);

    /**
     * Prior to admin-configuration, should this filter be user-configurable on the specified page?
     *
     * @param string $page
     * @return bool
     */
    public static function getUserConfigurableInitialState($page);
}
