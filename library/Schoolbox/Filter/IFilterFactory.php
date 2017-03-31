<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;



/**
 * Each instance of this factory knows how to build a specific filter definition.
 *
 * Once build, the definition should be able to perform its intended end use without fetching any more data.
 *
 * It is up to the factory to fetch all data and inject it into the filter definition.
 */
interface IFilterFactory
{
    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * @return IFilterDefinition
     */
    public function buildForAdminPages();

    /**
     * Build a filter, ready to inject into a filter panel
     *
     * @param mixed[] $params - Factory must validate and convert each to calls to setters
     * @return IFilterDefinition
     */
    public function buildForFilterPanel(array $params = []);

    /**
     * Build a filter definition that is able to convert keys back to elements
     *
     * @return IFilterDefinition
     */
    public function buildForConvertingKeyToElement();
}
