<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Option\Selection;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;
use Schoolbox\Filter;

class Model
{
    use Id;

    /** @var Filter\Model */
    private $filter;

    /** @var User */
    private $user;

    /** @var string */
    private $page;

    /** @var string */
    private $key;

    /** @var bool */
    private $selected;

    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * @param Filter\Model $filter
     * @return $this
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return static
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return string
     */
    public function getPage()
    {
        return $this->page;
    }


    /**
     * @param string $page
     * @return static
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }


    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @param string $key
     * @return static
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }


    /**
     * @return bool
     */
    public function getSelected()
    {
        return $this->selected;
    }


    /**
     * @param bool $selected
     * @return static
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;
        return $this;
    }

}
