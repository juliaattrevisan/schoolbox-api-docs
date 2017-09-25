<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Permission\SiteAccess;

class Model
{

    /** @var string */
    private $siteSchoolCode;

    /** @var string */
    private $accessToken;


    /**
     * @return string
     */
    public function getSiteSchoolCode()
    {
        return $this->siteSchoolCode;
    }


    /**
     * @param string $siteSchoolCode
     * @return $this
     */
    public function setSiteSchoolCode($siteSchoolCode)
    {
        $this->siteSchoolCode = $siteSchoolCode;
        return $this;
    }


    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }


    /**
     * @param string $accessToken
     * @return $this
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }


}