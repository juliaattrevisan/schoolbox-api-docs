<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\SubType;

use JsonSerializable;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\Type as PastoralType;

class Model implements ISoftDeletable, JsonSerializable
{
    use Id;
    use DeletedAtDate;

    /** @var PastoralType\Model */
    private $pastoralType;

    /** @var string */
    private $name;

    /**
     * Optional. Null if not linked to SIS
     *
     * @var null|string
     */
    private $externalCode;

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
     * @param $pastoralType
     *
     * @return $this
     */
    public function setPastoralType($pastoralType)
    {
        $this->pastoralType = $pastoralType;

        return $this;
    }

    /**
     * @return PastoralType\Model
     */
    public function getPastoralType()
    {
        return $this->pastoralType;
    }

    /**
     * @param null|string $externalCode
     *
     * @return $this
     */
    public function setExternalCode($externalCode = null)
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getExternalCode()
    {
        return $this->externalCode;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return string[]
     */
    public function jsonSerialize()
    {
        return [
            'id'   => $this->getId(),
            'name' => $this->getName(),
        ];
    }
}
