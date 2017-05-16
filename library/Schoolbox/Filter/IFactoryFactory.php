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
     * Build a set of key converters for the specified page
     *
     * @param string $page
     * @return IKeyConverter[]|GenericCollection Indexed by filter.id
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     */
    public function buildKeyConverters($page);


    /**
     * Builds a filter panel for the specified user and page, which submits selections to the specified route
     *
     * @param User      $user
     * @param string    $page
     * @param string    $route
     * @param null|User $viewee
     * @param array     $query
     * @return Panel
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If filter definition slug not set
     */
    public function buildPanel(User $user, $page, $route, User $viewee = null, array $query = []);
}
