<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\User;

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


    public function __construct(IFilterDefinition $definition, User $user, $page)
    {
        $this->definition = $definition;
        $this->user       = $user;
        $this->page       = $page;
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
    protected function getDefinition()
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
}
