<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Page\Option\Defaults;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Filter\Page\Option\Model as FilterOption;

class Model
{
    use Id;

    /** @var FilterOption */
    private $filterOption;

    /** @var string */
    private $roleType;

    /** @var bool */
    private $selected;


    /**
     * @return FilterOption
     */
    public function getFilterOption()
    {
        return $this->filterOption;
    }


    /**
     * @param FilterOption $filterOption
     * @return $this
     */
    public function setFilterOption(FilterOption $filterOption)
    {
        $this->filterOption = $filterOption;
        return $this;
    }


    /**
     * @return string
     */
    public function getRoleType()
    {
        return $this->roleType;
    }


    /**
     * @param string $roleType
     * @return $this
     */
    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
        return $this;
    }


    /**
     * @return bool
     */
    public function isSelected()
    {
        return $this->getSelected();
    }


    /**
     * Mapper-compatible getter
     *
     * @return bool
     */
    public function getSelected()
    {
        return $this->selected;
    }


    /**
     * @param bool $selected
     * @return $this
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;
        return $this;
    }
}
