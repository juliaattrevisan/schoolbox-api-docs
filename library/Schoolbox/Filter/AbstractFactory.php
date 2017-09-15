<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;


abstract class AbstractFactory implements IFactory
{
    /** @var Page\Option\Mapper */
    private $optionMapper;

    /** @var Page\Option\Defaults\Mapper */
    private $defaultsMapper;

    /** @var Selection\Mapper */
    private $selectionMapper;


    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper
    ) {
        $this->optionMapper    = $optionMapper;
        $this->defaultsMapper  = $defaultMapper;
        $this->selectionMapper = $selectionMapper;
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
        Collection $selections,
        Collection $overrides
    ) {
        $filterId = $definition->getFilter()->getId();
        $filterSelections = $selections[$filterId] ?: $this->selectionMapper->createCollection();

        // Did controller or URL request to override saved user-selections ?
        $filterOverrides = $overrides[$filterId] ?: null;

        // If overrides are present, ignore the user's saved selections.
        $baseArgs = [
            'definition'            => $definition,
            'user'                  => $user,
            'pageFilter'            => $pageFilter,
            'selections'            => $filterOverrides ?: $filterSelections,
            'filterSelectionMapper' => $this->selectionMapper,
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
            'filterOptionMapper'  => $this->optionMapper,
            'filterDefaultMapper' => $this->defaultsMapper,
        ];
    }


    /**
     * @param User                                             $user
     * @param Page\Model                                       $pageFilter
     * @param IDefinition                                      $definition
     * @param Collection|GenericCollection|Selection\Model[][] $overrides  Indexed by filter.id, selection.key
     * @param null|User                                        $viewee
     * @return mixed[]
     */
    protected abstract function buildExtraComponentArgs(
        User $user,
        Page\Model $pageFilter,
        IDefinition $definition,
        Collection $overrides,
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
     * @return Page\Option\Mapper
     */
    protected function getOptionMapper(): Page\Option\Mapper
    {
        return $this->optionMapper;
    }


    /**
     * @return Page\Option\Defaults\Mapper
     */
    protected function getDefaultsMapper(): Page\Option\Defaults\Mapper
    {
        return $this->defaultsMapper;
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
