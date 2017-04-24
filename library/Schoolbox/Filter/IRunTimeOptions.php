<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

/**
 * Container for injecting dynamic filter options into a filter when being displayed in the filter panel.
 *
 * No interface methods. Each concrete implementation will be different. Eg:
 * * Terms    - no methods. All options are pre-defined
 * * WorkType - no methods. All options are pre-defined
 * * GroupClass - getClasses() + getGroups()
 */
interface IRunTimeOptions
{

    /**
     * Should we ignore the user's saved selections, and use the controller's injected selections instead?
     *
     * The Controller can override any filter component to enable jumping to a specific data set.
     *
     * @return bool
     */
    public function isSelectionOverride();

    /**
     * @return null|string[]
     */
    public function getOverrideKeys();
}
