<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

class Instance
{
    public const APPLICATION_NAME = 'schoolbox';

    /**
     * @var string
     */
    private string $id;
    /**
     * @var string
     */
    private string $hostname;
    /**
     * @var string
     */
    private string $domain;
    /**
     * @var string
     */
    private string $version;
    /**
     * @var string
     */
    private string $type;
    /**
     * @var string
     */
    private string $schoolCode;


    /**
     * @param string $id         the 8 character instance ID (same as INSTANCE_ID const)
     * @param string $hostname   hostname used to access server without the domain
     * @param string $domain     network domain that is used to access server remotely
     * @param string $version    version of Schoolbox currently installed
     * @param string $type       whether this system is production or development etc.
     * @param string $schoolCode Alaress' unique identifier for the School this instance runs for
     */
    public function __construct(
        string $id,
        string $hostname,
        string $domain,
        string $version,
        string $type,
        string $schoolCode
    ) {
        $this->id = $id;
        $this->hostname = $hostname;
        $this->domain = $domain;
        $this->version = $version;
        $this->type = $type;
        $this->schoolCode = $schoolCode;
    }


    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }


    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }


    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }


    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * @return string
     */
    public function getSchoolCode()
    {
        return $this->schoolCode;
    }


    /**
     * @return bool
     */
    public function isProduction()
    {
        return strpos($this->getType(), 'prod') !== false;
    }


    /**
     * @return bool
     */
    public function isDevelopment()
    {
        return strpos($this->getType(), 'dev') !== false;
    }


    /**
     * @return bool
     */
    public function isSandbox()
    {
        return strpos($this->getType(), 'sandbox') !== false;
    }


    /**
     * @return bool
     */
    public function isVagrant()
    {
        return strpos($this->getType(), 'vagrant') !== false;
    }
}
