<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

class Subject
{

    /** @var string */
    private $subjectCode;

    /** @var int */
    private $yearGroup;

    /** @var string */
    private $longName;

    /** @var string */
    private $classCode;

    /** @var string */
    private $subjectType;

    /** @var string */
    private $campusCode;


    /**
     * @param string $subjectCode
     * @param int    $yearGroup
     * @param string $longName
     * @param string $classCode
     * @param string $subjectType
     * @param string $campusCode
     */
    public function __construct(string $subjectCode, int $yearGroup, string $longName, string $classCode, string $subjectType, string $campusCode)
    {
        $this->subjectCode = $subjectCode;
        $this->yearGroup   = $yearGroup;
        $this->longName    = $longName;
        $this->classCode   = $classCode;
        $this->subjectType = $subjectType;
        $this->campusCode  = $campusCode;
    }


    /**
     * @return string
     */
    public function getSubjectCode(): string
    {
        return $this->subjectCode;
    }


    /**
     * @return int
     */
    public function getYearGroup(): int
    {
        return $this->yearGroup;
    }


    /**
     * @return string
     */
    public function getLongName(): string
    {
        return $this->longName;
    }


    /**
     * @return string
     */
    public function getClassCode(): string
    {
        return $this->classCode;
    }


    /**
     * @return string
     */
    public function getSubjectType(): string
    {
        return $this->subjectType;
    }


    /**
     * @return string
     */
    public function getCampusCode(): string
    {
        return $this->campusCode;
    }
}
