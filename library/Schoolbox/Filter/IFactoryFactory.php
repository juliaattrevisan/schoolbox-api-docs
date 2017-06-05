<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

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
     * Return a constant that represents the entity that is being filtered,
     * eg 1 => 'assessments'
     *
     * @return int
     */
    public static function getEntity();


    /**
     * A registry of assessment-filter factories, along with their construction dependencies.
     *
     * Registered by filter definition class name.
     *
     * @param string $filterDefinitionClass
     * @return IFactory
     * @throws \InvalidArgumentException if no filter definition class not found, or filter factory not registered
     * @throws \ReflectionException If the specified class does not exist
     */
    public function buildFilterFactoryForFilter($filterDefinitionClass);


    /**
     * Build a set of key converters for filters configured as enabled for the specified page
     *
     * Key Converters should be light-weight as they are instantiated on every filtered report request
     *
     * @param string $page
     * @return IKeyConverter[]|GenericCollection Indexed by filter.id
     */
    public function buildEnabledKeyConverters($page);


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
    public function buildCurrentState($page, $enabledOnly);


    /**
     * Builds a filter panel for the specified user and page, which submits selections to the specified route
     *
     * @param User      $user
     * @param string    $page
     * @param string    $route
     * @param null|User $viewee
     * @param array     $request
     * @return Panel
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined the entity it is filtering
     */
    public function buildPanel(User $user, $page, $route, User $viewee = null, array $request = []);

}
