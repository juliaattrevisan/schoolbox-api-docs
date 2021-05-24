<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

class Teacher
{

    /** @var string */
    private $class;

    /** @var int */
    private $companyCode;

    /** @var int */
    private $employeeCode;

    /** @var string */
    private $email;

    /** @var int */
    private $id;

    /** @var string */
    private $surname;

    /** @var string */
    private $teacherCode;

    /** @var string */
    private $teacherName;

    /** @var array */
    private $udText;

    /** @var int */
    private $yearGroup;

    /** @var string */
    private $yearGroupDescription;


    /**
     * @param string      $class
     * @param int         $companyCode
     * @param int         $employeeCode
     * @param string      $email
     * @param int         $id
     * @param string      $surname
     * @param string      $teacherCode
     * @param string      $teacherName
     * @param array       $udText
     * @param int         $yearGroup
     * @param string      $yearGroupDescription
     */
    public function __construct(
        string $class,
        int $companyCode,
        int $employeeCode,
        string $email,
        int $id,
        string $surname,
        string $teacherCode,
        string $teacherName,
        array $udText,
        int $yearGroup,
        string $yearGroupDescription
    ) {
        $this->class                = $class;
        $this->companyCode          = $companyCode;
        $this->employeeCode         = $employeeCode;
        $this->email                = $email;
        $this->id                   = $id;
        $this->surname              = $surname;
        $this->teacherCode          = $teacherCode;
        $this->teacherName          = $teacherName;
        $this->udText               = $udText;
        $this->yearGroup            = $yearGroup;
        $this->yearGroupDescription = $yearGroupDescription;
    }


    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }


    /**
     * @return int
     */
    public function getCompanyCode(): int
    {
        return $this->companyCode;
    }


    /**
     * @return int
     */
    public function getEmployeeCode(): int
    {
        return $this->employeeCode;
    }


    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getSurname(): string
    {
        return $this->surname;
    }


    /**
     * @return string
     */
    public function getTeacherCode(): string
    {
        return $this->teacherCode;
    }


    /**
     * @return string
     */
    public function getTeacherName(): string
    {
        return $this->teacherName;
    }


    /**
     * @return array
     */
    public function getUdText(): array
    {
        return $this->udText;
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
    public function getYearGroupDescription(): string
    {
        return $this->yearGroupDescription;
    }
}
