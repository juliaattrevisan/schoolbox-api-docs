<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Selection;

use Schoolbox\User;
use Schoolbox\Filter;

class Model
{

    /** @var Filter\Model */
    private $filter;

    /** @var User */
    private $user;

    /** @var string */
    private $page;

    /**
     * A concrete key, eg '1', '3' that matches 1 entity
     * This entity may be fictional, eg an assessment has no WorkType, a selection will exist with key 'None'
     *
     * @var string
     */
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;
        return $this;
    }
}
