<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\User;
use Schoolbox\Filter;


/**
 * Responsible for building concrete implementation of the filter interfaces.
 *
 * All DB access should happen in factory, with results injected into objects being built.
 */
interface IFilterFactory
{
    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * No dynamic options or user-selections relevant.
     *
     * @param Filter\Model $filter
     * @return IFilterDefinition
     */
    public function buildDefinition(Filter\Model $filter);

    /**
     * Build a filter, ready to inject into a filter panel
     *
     * @param User $user
     * @param string $page
     * @param null|IRunTimeOptions $runTimeOptions
     * @return IFilterDefinition
     */
    public function buildComponent(User $user, $page, IRunTimeOptions $runTimeOptions = null);

    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @return IKeyConverter
     */
    public function buildKeyConverter();
}
