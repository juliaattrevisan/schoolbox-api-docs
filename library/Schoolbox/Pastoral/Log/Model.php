<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Log;

class Model {

    /** @var int */
    protected $id;

    /** @var int */
    protected $importId;

    /** @var int */
    protected $pastoralId;

    /** @var int */
    protected $userId;

    /** @var string */
    protected $studentName;

    /** @var string */
    protected $creatorName;

    /** @var string */
    protected $heading;

    /** @var string */
    protected $result;

    /** @var \Alaress_Date_Format */
    protected $createdAt;

    /**
     * @param string $heading
     * @return self
     */
    public function setHeading($heading) {
        $this->heading = $heading;
        return $this;
    }

    /**
     * @return string
     */
    public function getHeading() {
        return $this->heading;
    }

    /**
     * @param string $result
     * @return self
     */
    public function setResult($result) {
        $this->result = $result;
        return $this;
    }

    /**
     * @return string
     */
    public function getResult() {
        return $this->result;
    }
    /**
     * @param string $creatorName
     * @return self
     */
    public function setCreatorName($creatorName) {
        $this->creatorName = $creatorName;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatorName() {
        return $this->creatorName;
    }

    /**
     * @param string $studentName
     * @return self
     */
    public function setStudentName($studentName) {
        $this->studentName = $studentName;
        return $this;
    }

    /**
     * @return string
     */
    public function getStudentName() {
        return $this->studentName;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getImportId() {
        return $this->importId;
    }

    /**
     * @param int $importId
     * @return self
     */
    public function setImportId($importId) {
        $this->importId = $importId;
        return $this;
    }

    /**
     * @return int
     */
    public function getPastoralId() {
        return $this->pastoralId;
    }

    /**
     * @param int $pastoralId
     * @return self
     */
    public function setPastoralId($pastoralId) {
        $this->pastoralId = $pastoralId;
        return $this;
    }

    /**
     * @return int
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * @param int $userId
     * @return self
     */
    public function setUserId($userId) {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @param \Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format $createdAt
     */
    public function getCreatedAt() {
        return $this->createdAt;
    }
}
