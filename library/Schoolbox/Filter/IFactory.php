<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
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
    public function buildDefinition(Filter\Model $filter): IDefinition;


    /**
     * Build a filter component, ready to inject into a filter panel
     *
     * $selections:
     * * the user's saved selections fetched from database
     * * cannot insert items
     * * ignored if $overrides are present, or if $pageFilter is not user-configurable
     *
     * $overrides:
     * * may insert items (so long as logic - eg permissions - permit)
     * * if non-null, ignore $selections
     *
     * @param IDefinition                              $definition They type of filter we are building
     * @param User                                     $user
     * @param Page\Model                               $pageFilter
     * @param GenericCollection|Selection\Model[]      $selections Indexed selection.key
     * @param null|User                                $viewee
     * @param null|GenericCollection|Selection\Model[] $overrides  Indexed selection.key
     * @return IComponent
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        GenericCollection $selections,
        ?User $viewee,
        ?GenericCollection $overrides
    ): IComponent;


    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @param Filter\Model $filter
     * @return IKeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): IKeyConverter;
}
