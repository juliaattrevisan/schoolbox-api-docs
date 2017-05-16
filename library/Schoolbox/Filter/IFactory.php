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
interface IFactory
{

    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * No dynamic options or user-selections relevant.
     *
     * @param Filter\Model $filter
     * @return IDefinition
     */
    public function buildDefinition(Filter\Model $filter);


    /**
     * Build a filter component, ready to inject into a filter panel
     *
     * Returns NULL if no options would be available
     *
     * @param string               $definitionClass  They type of filter we are building
     * @param User                 $user
     * @param string               $page
     * @param null|User            $viewee
     * @param null|string[]        $selectionOverrides
     * @return IComponent|null
     */
    public function buildComponent($definitionClass, User $user, $page, User $viewee = null, array $selectionOverrides = null);


    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @param Filter\Model $filter
     * @return IKeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter);
}
