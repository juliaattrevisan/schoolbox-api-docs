<?php

/**
 * A representation of a resource booking category.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Category;

use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;
use JsonSerializable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class Model implements ISoftDeletable, JsonSerializable
{
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Alaress_Date_Format
     */
    protected $modifiedAt;

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  Alaress_Date_Format $modifiedAt
     * @return $this
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * @param  string $name
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
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array(
            'id'   => $this->getId(),
            'name' => $this->getName(),
        );
    }
}
