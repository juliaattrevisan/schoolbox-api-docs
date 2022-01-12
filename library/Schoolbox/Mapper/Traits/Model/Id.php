<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

/**
 * Trait for model classes - adds an id field.
 */
trait Id
{
    /**
     * Identifier, ID, primary key, whatever you want to call it.
     *
     * Will be null if the model isn't yet persisted.
     *
     * @var int|null
     */
    protected $id;

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  int|null $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
}
