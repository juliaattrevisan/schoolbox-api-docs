<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;

use Schoolbox\Resource\Storage\IStorage;

/**
 * This class is ONLY used to get data into/out of the database.
 *
 * @package Schoolbox\MobileApp
 */
class Service
{

    /** @var int */
    private $id;

    /** @var string */
    private $appIdentifier;

    /** @var string */
    private $providerName;

    /** @var string */
    private $key;

    /** @var IStorage */
    private $certificate;


    /**
     * @param int $id
     * @return static
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
     * @return string
     */
    public function getAppIdentifier()
    {
        return $this->appIdentifier;
    }


    /**
     * @param string $appIdentifier
     * @return static
     */
    public function setAppIdentifier($appIdentifier)
    {
        $this->appIdentifier = $appIdentifier;
        return $this;
    }


    /**
     * @return string
     */
    public function getProviderName()
    {
        return $this->providerName;
    }


    /**
     * @param string $providerName
     * @return static
     */
    public function setProviderName($providerName)
    {
        $this->providerName = $providerName;
        return $this;
    }


    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @param string $key
     * @return static
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }


    /**
     * @return IStorage
     */
    public function getCertificate()
    {
        return $this->certificate;
    }


    /**
     * @param IStorage $certificate
     * @return static
     */
    public function setCertificate($certificate)
    {
        $this->certificate = $certificate;
        return $this;
    }
}
