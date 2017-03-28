<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Tags;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class Model implements ISoftDeletable, \JsonSerializable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var int */
    protected $pastoralId;

    /** @var string */
    protected $name;

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getPastoralId() {
        return $this->pastoralId;
    }

    /**
     * @param int $pastoralId
     * @return $this
     */
    public function setPastoralId($pastoralId) {
        $this->pastoralId = $pastoralId;
        return $this;
    }

    /**
     * @return array
     */
    function jsonSerialize()
    {
        return [
            'id'   => $this->getId(),
            'name' => $this->getName(),
        ];
    }
}
