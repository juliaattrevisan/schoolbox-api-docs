<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Tag;

use JsonSerializable;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\Model as Pastoral;

class Model implements ISoftDeletable, JsonSerializable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var Pastoral
     */
    protected $pastoral;

    /**
     * @var string
     */
    protected $name;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }

    /**
     * @param Pastoral $pastoral
     *
     * @return $this
     */
    public function setPastoral($pastoral)
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * Tags can be spawned inside a selectize. If it returns a id/name tuple it can't work out when a new tag
     * name is an id or an actual name
     *
     * @return array
     */
    function jsonSerialize()
    {
        return [
            'id'   => $this->getName(),
            'name' => $this->getName(),
        ];
    }
}
