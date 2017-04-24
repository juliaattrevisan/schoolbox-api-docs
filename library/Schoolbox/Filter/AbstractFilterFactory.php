<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\User;


abstract class AbstractFilterFactory implements IFilterFactory
{

    /** @var Filter\Mapper */
    private $filterMapper;

    /** @var Option\Mapper */
    private $filterOptionMapper;

    /** @var Defaults\Mapper */
    private $filterDefaultMapper;

    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper
    ) {
        $this->filterMapper          = $filterMapper;
        $this->filterOptionMapper    = $optionMapper;
        $this->filterDefaultMapper   = $defaultMapper;
        $this->filterSelectionMapper = $selectionMapper;
    }


    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * No dynamic options or user-selections relevant.
     *
     * @param Filter\Model $filter
     * @return IFilterDefinition|object
     */
    public function buildDefinition(Filter\Model $filter)
    {
        $definitionReflector = new \ReflectionClass($filter->getClass());
        return $definitionReflector->newInstanceArgs($this->buildDefinitionArgs($filter));
    }


    /**
     * @param string               $definitionClass  They type of filter we are building
     * @param User                 $user
     * @param string               $page
     * @param IRunTimeOptions|null $runTimeOptions
     * @return IFilterComponent|object
     * @throws Mapper\Exception If a mapper column name is invalid
     */
    public function buildComponent($definitionClass, User $user, $page, IRunTimeOptions $runTimeOptions = null)
    {
        $filter     = $this->filterMapper->findByClass($definitionClass);
        $definition = $this->buildDefinition($filter);

        $overrideUserSelections = $runTimeOptions && $runTimeOptions->isSelectionOverride();

        $baseArgs = [
            'definition'            => $definition,
            'user'                  => $user,
            'page'                  => $page,
            'savedSelections'       => $overrideUserSelections ? null : $this->getSavedSelections($filter, $user, $page),
            'selectionOverrides'    => $overrideUserSelections ? $runTimeOptions->getOverrideKeys() : null,
            'filterSelectionMapper' => $this->filterSelectionMapper,
        ];

        $extraArgs = $this->buildExtraComponentArgs($user, $page, $runTimeOptions, $definition);

        $componentClass = $this->getAdjacentClass($definitionClass, 'Component');

        $componentReflector = new \ReflectionClass($componentClass);
        return $componentReflector->newInstanceArgs(array_merge($baseArgs, $extraArgs));
    }

    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @param Filter\Model $filter
     * @return IKeyConverter|object
     */
    public function buildKeyConverter(Filter\Model $filter)
    {
        $keyConverterReflector = new \ReflectionClass($this->getAdjacentClass($filter->getClass(), 'KeyConverter'));
        return $keyConverterReflector->newInstanceArgs($this->buildKeyConverterArgs($filter));
    }


    /**
     * @param Filter\Model $filter
     * @return mixed[]
     */
    protected function buildDefinitionArgs(Filter\Model $filter)
    {
        return [
            'filter'              => $filter,
            'filterOptionMapper'  => $this->filterOptionMapper,
            'filterDefaultMapper' => $this->filterDefaultMapper,
        ];
    }

    /**
     * @param User                 $user
     * @param string               $page
     * @param IRunTimeOptions|null $runTimeOptions
     * @param IFilterDefinition    $definition
     * @return mixed[]
     */
    protected abstract function buildExtraComponentArgs(User $user, $page, IRunTimeOptions $runTimeOptions = null, IFilterDefinition $definition);


    /**
     * Builds constructor arguments for KeyConverter
     *
     * @param Filter\Model $filter
     * @return mixed[]
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
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

    /**
     * Get user's existing selections for this filter and page
     *
     * @param Filter\Model $filter
     * @param User         $user
     * @param string       $page
     * @return GenericCollection|Selection\Model[]
     * @throws Mapper\Exception
     */
    private function getSavedSelections(Filter\Model $filter, User $user, $page)
    {
        return $this->filterSelectionMapper->findByColumnList([
            'filter_id' => $filter->getId(),
            'user_id'   => $user->getId(),
            'page'      => $page,
        ])->indexBy('key');
    }
}
