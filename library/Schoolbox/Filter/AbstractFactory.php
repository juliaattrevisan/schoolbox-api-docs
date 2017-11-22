<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
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
     * @param IDefinition                              $definition
     * @param User                                     $user
     * @param Page\Model                               $pageFilter
     * @param null|GenericCollection|Selection\Model[] $selections Indexed selection.key
     * @param null|User                                $viewee
     * @param null|GenericCollection|Selection\Model[] $overrides  Indexed selection.key
     * @return IComponent
     * @throws \InvalidArgumentException If neither $selections or $overrides are supplied
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        ?GenericCollection $overrides
    ): IComponent {
        return new BaseComponent($definition, $user, $pageFilter, $selections, $overrides, $this->selectionMapper);
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
     * @return Selection\Mapper
     */
    protected function getSelectionMapper(): Selection\Mapper
    {
        return $this->selectionMapper;
    }
}
