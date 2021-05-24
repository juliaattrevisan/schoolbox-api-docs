<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\School;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /** @var string */
    protected $name;

    /**
     * Model constructor.
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}
