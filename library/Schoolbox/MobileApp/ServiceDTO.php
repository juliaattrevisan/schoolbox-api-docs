<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\MobileApp;

use Alaress\Async\Notification\Cloud\ServiceInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage\IStorage;


/**
 * Class ServiceDTO
 *
 * This class is ONLY used to get data into the database.
 *
 * @package Schoolbox\MobileApp
 */
class ServiceDTO implements ServiceInterface
{

    /** @var string */
    private $appIdentifier;

    /** @var string */
    private $providerName;

    /** @var string */
    private $key;

    /** @var IStorage */
    private $certificate;


    /**
     * @param string   $appIdentifier
     * @param string   $providerName
     * @param string   $key
     * @param IStorage $certificate
     */
    public function __construct($appIdentifier, $providerName, $key = null, IStorage $certificate = null)
    {
        $this->appIdentifier = $appIdentifier;
        $this->providerName  = $providerName;
        $this->key           = $key;
        $this->certificate   = $certificate;
    }


    /**
     * @return string
     */
    public function getAppIdentifier()
    {
        return $this->appIdentifier;
    }


    /**
     * @return string
     */
    public function getProviderName()
    {
        return $this->providerName;
    }


    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @return IStorage
     */
    public function getCertificate()
    {
        return $this->certificate;
    }


    /**
     * @param array $tokens
     * @param array $data
     * @param int   $unreadCount
     * @return bool
     */
    public function send(array $tokens, array $data, $unreadCount = 0)
    {
        return false;
    }


    /**
     * @param LoggerInterface $log
     * @return $this
     */
    public function setLog(LoggerInterface $log)
    {
        return $this;
    }

}
