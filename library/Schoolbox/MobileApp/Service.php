<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Storage\IStorage;

/**
 * This class is ONLY used to get data into/out of the database.
 *
 * @package Schoolbox\MobileApp
 */
class Service implements ISoftDeletable
{
    use DeletedAtDate;

    /** @var int */
    private $id;

    /** @var string */
    private $appIdentifier;

    /** @var string */
    private $providerName;

    /**
     * Whatever key this mobile app uses: its format differs based on the
     * provider.
     *
     * @var string
     */
    private $key;

    /**
     * Public key for this mobile app: only relevant for Firebase.
     *
     * This is a JavaScript object which may be copied from Firebase, via:
     * Project Settings > General > Your apps > (your app nickname) > SDK setup and configuration > Config.
     * This should be only the {, } and contents.
     */
    private ?string $publicKey = null;

    /**
     * Web push public key for this mobile app: only relevant for Firebase, it
     * is used for registering a device with Firebase.
     *
     * This is a key which may be generated within Firebase, via:
     * Project Settings > Cloud Messaging > Web configuration > Web push certificates > Generate key pair.
     * Paste its value into this field.
     */
    private ?string $webPushPublicKey = null;

    /**
     * Certificate file: relevant only for the APNS provider.
     *
     * @var IStorage
     */
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
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }


    /**
     * @param string|null $publicKey
     * @return $this
     */
    public function setPublicKey(?string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getWebPushPublicKey(): ?string
    {
        return $this->webPushPublicKey;
    }


    /**
     * @param string|null $webPushPublicKey
     * @return $this
     */
    public function setWebPushPublicKey(?string $webPushPublicKey): self
    {
        $this->webPushPublicKey = $webPushPublicKey;
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
