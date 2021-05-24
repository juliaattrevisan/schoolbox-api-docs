<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter;

use function in_array;

use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;

abstract class AbstractFactory implements IFactory
{
    /** @var Page\Option\Mapper */
    private $optionMapper;

    /** @var Page\Option\Defaults\Mapper */
    private $defaultsMapper;

    /** @var Selection\Mapper */
    private $selectionMapper;


    /**
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     */
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
     * @inheritDoc
     * @throws \InvalidArgumentException If neither $selections or $overrides are supplied
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): IComponent {
        return new BaseComponent(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper()
        );
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


    /**
     * Returns whether the specified page displays a list of folders related to the viewee
     *
     * @param string $page
     * @return bool
     */
    protected function isInvolvedFolderPage(string $page): bool
    {
        return in_array($page, Page\Model::getInvolvedFoldersPages(), true);
    }


    /**
     * Returns whether the specified page relates to a single folder
     *
     * @param string $page
     * @return bool
     */
    protected function isSingleFolderPage(string $page): bool
    {
        return in_array($page, Page\Model::getSingleFolderPages(), true);
    }


    /**
     * Finds a returns the first folder model from an array of route args
     *
     * @param array $routeArgs
     * @return null|Folder\Model
     */
    protected function findFolderModel(array $routeArgs): ?Folder\Model
    {
        foreach ($routeArgs as $routeArg) {
            if ($routeArg instanceof Folder\Model) {
                return $routeArg;
            }
        }
        return null;
    }


    /**
     * Finds a returns the first course specification model from an array of route args
     *
     * @param array $routeArgs
     * @return CourseSpec\Model
     */
    protected function findCourseSpecModel(array $routeArgs): ?CourseSpec\Model
    {
        foreach ($routeArgs as $routeArg) {
            if ($routeArg instanceof CourseSpec\Model) {
                return $routeArg;
            }
        }
        return null;
    }
}
