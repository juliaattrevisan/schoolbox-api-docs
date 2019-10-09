<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * A FilterFactoryFactory knows how to build filter architecture objects for the entity that is being filtered.
 *
 * eg. The Assessment FilterFactoryFactory knows how to build all the assessment filters
 */
interface IFactoryFactory
{
    /**
     * A registry of assessment-filter factories, along with their construction dependencies.
     *
     * Registered by filter definition class name.
     *
     * @param string $filterDefinitionClass
     * @return IFactory
     * @throws InvalidArgumentException if no filter definition class not found, or filter factory not registered
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory;


    /**
     * @return Codec
     */
    public function buildCodec(): Codec;

    /**
     * Build a set of key converters for filters configured as enabled for the specified page
     *
     * Key Converters should be light-weight as they are instantiated on every filtered report request
     *
     * @param string $page
     * @return IKeyConverter[]|GenericCollection Indexed by filter.id
     */
    public function buildEnabledKeyConverters($page): GenericCollection;


    /**
     * Converts filter Selection models into an array of filters that can be injected into an IdFinder
     *
     * @param Collection|GenericCollection[]|Selection\Model[][] $selectionsPerFilter Indexed by filter.id then selection.key
     * @param User                                               $user
     * @param string                                             $page
     * @return null[]|Collection[]  Indexed by filter.class
     */
    public function convertSelectionsToFilters(Collection $selectionsPerFilter, User $user, string $page): array;

    /**
     * Build the current state of filters for a page, regardless of whether the state
     * is saved in the admin, or derived from initial state logic.
     *
     * For admin pages, we want all pageFilters, for public page, we just want the enabled ones.
     *
     * @param string $page
     * @param bool   $enabledOnly
     * @return GenericCollection|IDefinition[]  Indexed by filter.class
     */
    public function buildCurrentState($page, $enabledOnly): GenericCollection;


    /**
     * Builds a filter panel for the specified user and page, which submits selections to the specified route
     *
     * @param User      $user
     * @param string    $page
     * @param string    $route
     * @param null|User $viewee
     * @param array     $routeArgs  Any route params, as received by the controller method
     * @param array     $request
     * @return Panel
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \DomainException If this class has not defined the entity it is filtering
     */
    public function buildPanel(User $user, string $page, string $route, ?User $viewee, array $routeArgs, array $request = []): Panel;

}
