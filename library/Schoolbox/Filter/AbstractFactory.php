<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;


abstract class AbstractFactory implements IFactory
{

    /** @var Mapper */
    private $filterMapper;

    /** @var Page\Mapper */
    private $filterPageMapper;

    /** @var Page\Option\Mapper */
    private $filterOptionMapper;

    /** @var Page\Option\Defaults\Mapper */
    private $filterDefaultMapper;

    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    public function __construct(
        Mapper $filterMapper,
        Page\Mapper $pageMapper,
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper
    ) {
        $this->filterMapper          = $filterMapper;
        $this->filterPageMapper      = $pageMapper;
        $this->filterOptionMapper    = $optionMapper;
        $this->filterDefaultMapper   = $defaultMapper;
        $this->filterSelectionMapper = $selectionMapper;
    }


    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * No dynamic options or user-selections relevant.
     *
     * @param Model $filter
     * @return IDefinition|object
     * @throws \ReflectionException If unable to create an instance of the reflected class
     */
    public function buildDefinition(Model $filter)
    {
        $definitionReflector = new \ReflectionClass($filter->getClass());
        return $definitionReflector->newInstanceArgs($this->buildDefinitionArgs($filter));
    }


    /**
     * @inheritdoc
     * @throws \ReflectionException If unable to create an instance of the reflected class
     * @throws MapperException If invalid columns names are used
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        User $viewee = null,
        GenericCollection $selections,
        GenericCollection $overrides = null
    ) {
        // If overrides are present, ignore the user's saved selections.
        $baseArgs = [
            'definition'            => $definition,
            'user'                  => $user,
            'pageFilter'            => $pageFilter,
            'selections'            => $overrides ?: $selections,
            'filterSelectionMapper' => $this->filterSelectionMapper,
        ];

        $extraArgs = $this->buildExtraComponentArgs($user, $pageFilter, $definition, $overrides, $viewee);

        $componentClass = $this->getAdjacentClass(get_class($definition), 'Component');

        $componentReflector = new \ReflectionClass($componentClass);
        return $componentReflector->newInstanceArgs(array_merge($baseArgs, $extraArgs));
    }


    /**
     * @inheritdoc
     * @throws \ReflectionException
     */
    public function buildKeyConverter(Model $filter)
    {
        $keyConverterReflector = new \ReflectionClass($this->getAdjacentClass($filter->getClass(), 'KeyConverter'));
        return $keyConverterReflector->newInstanceArgs($this->buildKeyConverterArgs($filter));
    }


    /**
     * @param Model $filter
     * @return mixed[]
     */
    protected function buildDefinitionArgs(Model $filter)
    {
        return [
            'filter'              => $filter,
            'filterOptionMapper'  => $this->filterOptionMapper,
            'filterDefaultMapper' => $this->filterDefaultMapper,
        ];
    }


    /**
     * @param User                                     $user
     * @param Page\Model                               $pageFilter
     * @param IDefinition                              $definition
     * @param null|GenericCollection|Selection\Model[] $overrides
     * @param null|User                                $viewee
     * @return mixed[]
     */
    protected abstract function buildExtraComponentArgs(
        User $user,
        Page\Model $pageFilter,
        IDefinition $definition,
        GenericCollection $overrides = null,
        User $viewee = null
    );


    /**
     * Builds constructor arguments for KeyConverter
     *
     * @param Model $filter
     * @return mixed[]
     */
    protected function buildKeyConverterArgs(Model $filter)
    {
        return [
            'filter' => $filter,
        ];
    }


    /**
     * Given some class in some namespace, get the class name for an adjacent class in the same namespace
     *
     * @param string $reference
     * @param string $target
     * @return string
     */
    private function getAdjacentClass($reference, $target)
    {
        $namespace = substr($reference, 0, strrpos($reference, '\\'));
        return "{$namespace}\\{$target}";
    }
}
