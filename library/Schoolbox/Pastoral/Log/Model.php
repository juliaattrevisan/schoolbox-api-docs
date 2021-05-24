<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Log;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Model
{
    use Id;
    use CreatedAtDate;

    /**
     * Intentionally allows null to represent import failures, eg result = 'ERROR'
     *
     * @var null|int
     * @todo Consider normalising table into 'pastoral_import' + 'pastoral_import_log'
     */
    private $importId;

    /**
     * Intentionally allows null for items not associated with a pastoral record, eg 'start of import'
     *
     * @var null|int
     * @todo Consider normalising table into 'pastoral_import' + 'pastoral_import_log' so this isn't required.
     */
    private $pastoralId;

    /**
     * Intentionally permits nulls, eg when an import fails
     *
     * @var null|int
     */
    private $userId;

    /**
     * @var null|string
     */
    private $studentName;

    /** @var null|string */
    private $creatorName;

    /** @var null|string */
    private $heading;

    /** @var string */
    private $result;

    /**
     * @param null|string $heading
     *
     * @return self
     */
    public function setHeading($heading = null)
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * @param string $result
     *
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
     * @param string $creatorName
     *
     * @return self
     */
    public function setCreatorName($creatorName)
    {
        $this->creatorName = $creatorName;

        return $this;
    }

    /**
     * @return string
     */
    public function getCreatorName()
    {
        return $this->creatorName;
    }

    /**
     * @param string $studentName
     *
     * @return self
     */
    public function setStudentName($studentName)
    {
        $this->studentName = $studentName;

        return $this;
    }

    /**
     * @return string
     */
    public function getStudentName()
    {
        return $this->studentName;
    }

    /**
     * @return int
     */
    public function getImportId()
    {
        return $this->importId;
    }

    /**
     * @param int $importId
     *
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
    public function getPastoralId()
    {
        return $this->pastoralId;
    }

    /**
     * @param null|int $pastoralId
     *
     * @return $this
     */
    public function setPastoralId($pastoralId = null)
    {
        $this->pastoralId = $pastoralId;

        return $this;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     *
     * @return self
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }
}
