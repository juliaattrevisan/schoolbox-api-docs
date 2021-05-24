<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Models;

class StudentAddress
{

    /** @var string */
    private $address1;

    /** @var string */
    private $address2;

    /** @var string */
    private $address3;

    /** @var string */
    private $contactType;

    /** @var string */
    private $email;

    /** @var string */
    private $name;

    /** @var string */
    private $occupationPhone;

    /** @var string */
    private $phone;

    /** @var string */
    private $phoneAlt;

    /** @var int */
    private $postCode;

    /** @var string */
    private $spouseBusinessPhone;

    /** @var string */
    private $spouseEmail;

    /** @var string */
    private $spouseId;

    /** @var string */
    private $spouseMobilePhone;

    /** @var string */
    private $spousePreferred;

    /** @var string */
    private $spouseRelation;

    /** @var string */
    private $spouseSurname;

    /** @var string */
    private $spouseTitle;

    /** @var string */
    private $state;

    /** @var string */
    private $suburb;

    /** @var string */
    private $validAddressFlag;


    /**
     * @param string $address1
     * @param string $address2
     * @param string $address3
     * @param string $contactType
     * @param string $email
     * @param string $name
     * @param string $occupationPhone
     * @param string $phone
     * @param string $phoneAlt
     * @param int    $postCode
     * @param string $spouseBusinessPhone
     * @param string $spouseEmail
     * @param string $spouseId
     * @param string $spouseMobilePhone
     * @param string $spousePreferred
     * @param string $spouseRelation
     * @param string $spouseSurname
     * @param string $spouseTitle
     * @param string $state
     * @param string $suburb
     * @param string $validAddressFlag
     */
    public function __construct(
        string $address1,
        string $address2,
        string $address3,
        string $contactType,
        string $email,
        string $name,
        string $occupationPhone,
        string $phone,
        string $phoneAlt,
        int $postCode,
        string $spouseBusinessPhone,
        string $spouseEmail,
        string $spouseId,
        string $spouseMobilePhone,
        string $spousePreferred,
        string $spouseRelation,
        string $spouseSurname,
        string $spouseTitle,
        string $state,
        string $suburb,
        string $validAddressFlag
    ) {
        $this->address1            = $address1;
        $this->address2            = $address2;
        $this->address3            = $address3;
        $this->contactType         = $contactType;
        $this->email               = $email;
        $this->name                = $name;
        $this->occupationPhone     = $occupationPhone;
        $this->phone               = $phone;
        $this->phoneAlt            = $phoneAlt;
        $this->postCode            = $postCode;
        $this->spouseBusinessPhone = $spouseBusinessPhone;
        $this->spouseEmail         = $spouseEmail;
        $this->spouseId            = $spouseId;
        $this->spouseMobilePhone   = $spouseMobilePhone;
        $this->spousePreferred     = $spousePreferred;
        $this->spouseRelation      = $spouseRelation;
        $this->spouseSurname       = $spouseSurname;
        $this->spouseTitle         = $spouseTitle;
        $this->state               = $state;
        $this->suburb              = $suburb;
        $this->validAddressFlag    = $validAddressFlag;
    }


    /**
     * @return string
     */
    public function getAddress1(): string
    {
        return $this->address1;
    }


    /**
     * @return string
     */
    public function getAddress2(): string
    {
        return $this->address2;
    }


    /**
     * @return string
     */
    public function getAddress3(): string
    {
        return $this->address3;
    }


    /**
     * @return string
     */
    public function getContactType(): string
    {
        return $this->contactType;
    }


    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getOccupationPhone(): string
    {
        return $this->occupationPhone;
    }


    /**
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }


    /**
     * @return string
     */
    public function getPhoneAlt(): string
    {
        return $this->phoneAlt;
    }


    /**
     * @return int
     */
    public function getPostCode(): int
    {
        return $this->postCode;
    }


    /**
     * @return string
     */
    public function getSpouseBusinessPhone(): string
    {
        return $this->spouseBusinessPhone;
    }


    /**
     * @return string
     */
    public function getSpouseEmail(): string
    {
        return $this->spouseEmail;
    }


    /**
     * @return string
     */
    public function getSpouseId(): string
    {
        return $this->spouseId;
    }


    /**
     * @return string
     */
    public function getSpouseMobilePhone(): string
    {
        return $this->spouseMobilePhone;
    }


    /**
     * @return string
     */
    public function getSpousePreferred(): string
    {
        return $this->spousePreferred;
    }


    /**
     * @return string
     */
    public function getSpouseRelation(): string
    {
        return $this->spouseRelation;
    }


    /**
     * @return string
     */
    public function getSpouseSurname(): string
    {
        return $this->spouseSurname;
    }


    /**
     * @return string
     */
    public function getSpouseTitle(): string
    {
        return $this->spouseTitle;
    }


    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getSuburb(): string
    {
        return $this->suburb;
    }


    /**
     * @return string
     */
    public function getValidAddressFlag(): string
    {
        return $this->validAddressFlag;
    }
}
