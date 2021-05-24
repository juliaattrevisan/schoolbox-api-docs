<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Eportfolio\Import\Log;

class Model
{

    /** @var int */
    protected $id;

    /** @var int */
    protected $importId;

    /** @var string */
    protected $result;

    /** @var \Alaress_Date_Format */
    protected $createdAt;

    /** @var \Alaress_Date_Format */
    protected $updatedAt;

    /**
     * @param string $result
     * @return self
     */
    public function setResult($result)
    {
        $this->result = $result;
        return $this;
    }

    /**
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param int $importId
     * @return self
     */
    public function setImportId($importId)
    {
        $this->importId = $importId;
        return $this;
    }

    /**
     * @return int
     */
    public function getImportId()
    {
        return $this->importId;
    }

    /**
     * @param int $id
     * @return self
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
     * @param \Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \Alaress_Date_Format $updatedAt
     * @return self
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
