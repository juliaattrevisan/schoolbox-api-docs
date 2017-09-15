<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
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
     * $selections[filterId]:
     * * cannot insert items
     * * ignored if $overrides are present, or if $pageFilter is not user-configurable
     *
     * $overrides[filterId]:
     * * can insert items
     * * if non-null, ignore $selections
     *
     * @param IDefinition                                      $definition They type of filter we are building
     * @param User                                             $user
     * @param Page\Model                                       $pageFilter
     * @param null|User                                        $viewee
     * @param Collection|GenericCollection|Selection\Model[][] $selections Indexed by filter.id, selection.key
     * @param Collection|GenericCollection|Selection\Model[][] $overrides  Indexed by filter.id, selection.key
     * @return IComponent|null
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        User $viewee = null,
        Collection $selections,
        Collection $overrides
    );


    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @param Filter\Model $filter
     * @return IKeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): IKeyConverter;
}
