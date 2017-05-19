<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Category;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use JsonSerializable;

class Model implements ISoftDeletable, JsonSerializable
{
    use Id;
    use DeletedAtDate;
    
    /** @var PastoralType */
    protected $pastoralType;

    /** @var string */
    protected $name;

    /** @var string */
    protected $externalCode;

    /**
     * @param string $name
     * @return self
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
     * @param PastoralType $pastoralType
     * @return self
     */
    public function setPastoralType($pastoralType) {
        $this->pastoralType = $pastoralType;
        return $this;
    }

    /**
     * @return PastoralType
     */
    public function getPastoralType() {
        return $this->pastoralType;
    }

    /**
     * @param string $externalCode
     * @return self
     */
    public function setExternalCode($externalCode) {
        $this->externalCode = $externalCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getExternalCode() {
        return $this->externalCode;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize() {
        return array(
            'id'          => $this->getId(),
            'name'        => $this->getName(),
        );
    }
}
