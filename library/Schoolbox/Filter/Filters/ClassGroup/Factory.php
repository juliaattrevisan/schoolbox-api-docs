<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\ClassGroup;

use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\IRunTimeOptions;

/**
 * A factory for building TermFilters
 */
class Factory extends AbstractFilterFactory
{

    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * @return IFilterDefinition
     */
    public function buildDefinition()
    {
        // TODO: Implement buildForAdminPages() method.
    }


    /**
     * Build a filter, ready to inject into a filter panel
     *
     * @param null|IRunTimeOptions $runTimeOptions
     * @return IFilterDefinition
     */
    public function buildComponent(IRunTimeOptions $runTimeOptions = null)
    {
        // TODO: Implement buildForFilterPanel() method.
    }
}
