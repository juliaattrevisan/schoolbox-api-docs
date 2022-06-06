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

    public const SITE_TYPE_DEVELOPMENT  = 'dev';

    public const SITE_TYPE_PRODUCTION   = 'prod';

    public const SITE_TYPE_STAGING      = 'stg';

    public const SITE_TYPE_TRIAL        = 'trial';

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

    /** @var string */
    private string $siteEnvironment;


    /**
     * @param string $id         the 8 character instance ID (same as INSTANCE_ID const)
     * @param string $hostname   hostname used to access server without the domain
     * @param string $domain     network domain that is used to access server remotely
     * @param string $version    version of Schoolbox currently installed
     * @param string $type       whether this system is production or development etc.
     * @param string $schoolCode Alaress' unique identifier for the School this instance runs for
     * @param string $siteEnvironment whether on-prem or cloud
     */
    public function __construct(
        string $id,
        string $hostname,
        string $domain,
        string $version,
        string $type,
        string $schoolCode,
        string $siteEnvironment,
    ) {
        $this->id = $id;
        $this->hostname = $hostname;
        $this->domain = $domain;
        $this->version = $version;
        $this->type = $type;
        $this->schoolCode = $schoolCode;
        $this->siteEnvironment = $siteEnvironment;
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
    public function isProduction(): bool
    {
        return strpos($this->getType(), self::SITE_TYPE_PRODUCTION) !== false;
    }


    /**
     * @return bool
     */
    public function isStaging(): bool
    {
        return strpos($this->getType(), self::SITE_TYPE_STAGING) !== false;
    }


    /**
     * @return bool
     */
    public function isTrial(): bool
    {
        return strpos($this->getType(), self::SITE_TYPE_TRIAL) !== false;
    }


    /**
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return strpos($this->getType(), self::SITE_TYPE_DEVELOPMENT) !== false;
    }

    /**
     * @return string
     */
    public function getSiteEnvironment(): string
    {
        return $this->siteEnvironment;
    }
}
