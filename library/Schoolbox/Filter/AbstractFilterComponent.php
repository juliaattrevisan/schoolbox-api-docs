<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Filter\Selection;

/**
 * Common logic for Filter Components
 */
abstract class AbstractFilterComponent implements IFilterComponent
{

    /**
     * @var IFilterDefinition
     */
    private $definition;

    /** @var User */
    private $user;

    /** @var string */
    private $page;

    /** @var GenericCollection|Selection\Model[] */
    private $savedSelections;

    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    /**
     * @param IFilterDefinition $definition
     * @param User              $user
     * @param string            $page
     * @param GenericCollection $savedSelections Existing selections for this filter, user and page
     * @param Selection\Mapper  $filterSelectionMapper
     */
    public function __construct(IFilterDefinition $definition, User $user, $page, GenericCollection $savedSelections, Selection\Mapper $filterSelectionMapper)
    {
        $this->definition            = $definition;
        $this->user                  = $user;
        $this->page                  = $page;
        $this->savedSelections       = $savedSelections;
        $this->filterSelectionMapper = $filterSelectionMapper;
    }


    /**
     * @inheritdoc
     */
    public function getTemplate()
    {
        return "@filters/panel/{$this->getTemplateFile()}";
    }


    /**
     * @return string
     */
    protected abstract function getTemplateFile();


    /**
     * @return IFilterDefinition
     */
    public function getDefinition()
    {
        return $this->definition;
    }


    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }


    /**
     * @return string
     */
    protected function getPage()
    {
        return $this->page;
    }


    /**
     * @return Selection\Model[]|GenericCollection
     */
    protected function getSavedSelections()
    {
        return $this->savedSelections;
    }


    /**
     * @return Selection\Mapper
     */
    protected function getFilterSelectionMapper()
    {
        return $this->filterSelectionMapper;
    }
}
