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
 * No interface methods. Each concrete implmentation will be different. Eg:
 * * Terms    - no methods. All options are pre-defined
 * * WorkType - no methods. All options are pre-defined
 * * GroupClass - getClasses() + getGroups()
 */
interface IRunTimeOptions {

}
