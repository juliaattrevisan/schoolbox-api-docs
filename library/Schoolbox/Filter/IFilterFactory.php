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
     * No dynamic options or user-selections relevant.
     *
     * @return IFilterDefinition
     */
    public function buildForAdminPages();

    /**
     * Build a filter, ready to inject into a filter panel
     *
     * @param null|IRunTimeOptions $runTimeOptions
     * @return IFilterDefinition
     */
    public function buildForFilterPanel(IRunTimeOptions $runTimeOptions = null);

    /**
     * Returns a key converter, able to convert between keys and the entities they represent
     *
     * @return IKeyConverter
     */
    public function buildKeyConverter();
}
