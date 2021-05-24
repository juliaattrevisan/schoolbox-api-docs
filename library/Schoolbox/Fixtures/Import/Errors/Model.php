<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Import\Errors;

class Model
{

    /** @var int */
    protected $id;

    /** @var int */
    protected $importId;

    /** @var string */
    protected $result;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $hash;

    /** @var string */
    protected $type;

    /** @var int */
    protected $line;

    /** @var int */
    protected $column;

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
     * @param string $filename
     * @return self
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $hash
     * @return self
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $line
     * @return self
     */
    public function setLine($line)
    {
        $this->line = $line;
        return $this;
    }

    /**
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @param int $column
     * @return self
     */
    public function setColumn($column)
    {
        $this->column = $column;
        return $this;
    }

    /**
     * @return int
     */
    public function getColumn()
    {
        return $this->column;
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
