<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

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
     * Returns a string by which to identify this filter
     *
     * @return string
     */
    public static function getSlug(): string;


    /**
     * Return the name of the filter to expose in the UI
     *
     * @return string
     */
    public static function getName(): string;


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
    public static function isMandatory(string $page): bool;


    /**
     * @return Model
     */
    public function getFilter(): Model;


    /**
     * Returns the twig template to render this filter's panel component into
     *
     * @return string
     */
    public static function getPanelComponentTemplate(): string;


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
    public function normalisePageOptions(Page\Model $pageFilter, string $roleTypeFilter = null): GenericCollection;


    /**
     * Return an ordered list of pages where this filter is available
     *
     * @param IConfigLoader $configLoader Schoolbox instance configuration.
     *                                    (Filters may be enabled or disabled by
     *                                     particular configuration settings.)
     * @return string[]|array
     * @see Page\Model::PAGE_* constants
     *
     */
    public static function getPages(IConfigLoader $configLoader): array;


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
     * @todo This should be a view-layer responsibility
     */
    public function getLabel(string $key): string;


    /**
     * Prior to admin-configuration, should this filter be enable on the specified page?
     *
     * @param string $page
     * @return bool
     */
    public static function getEnabledInitialState(string $page): bool;

    /**
     * Prior to admin-configuration, should this filter be user-configurable on the specified page?
     *
     * @param string $page
     * @return bool
     */
    public static function getUserConfigurableInitialState(string $page): bool;
}
