<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Provider;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    /**
     * DB representation of basic launch type.
     *
     * @var string
     */
    public const LAUNCH_TYPE_BASIC = 'LTI_LAUNCH_TYPE_BASIC';

    /**
     * DB representation of picker launch type.
     *
     * @var string
     */
    public const LAUNCH_TYPE_PICKER = 'LTI_LAUNCH_TYPE_PICKER';

    use Id;
    use DeletedAtDate;
    use CreatedAtDate;
    use UpdatedAtDate;

    /** @var string */
    protected $name;

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /** @var string */
    protected $custom;

    /**
     * @param string $custom
     * @return self
     */
    public function setCustom($custom)
    {
        $this->custom = $custom;
        return $this;
    }

    /**
     * @return string
     */
    public function getCustom()
    {
        return $this->custom;
    }

    /** @var string */
    protected $key;

    /**
     * @param string $key
     * @return self
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /** @var string */
    protected $secret;

    /**
     * @param string $secret
     * @return self
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /** @var string */
    protected $url;

    /**
     * @param string $url
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /** @var string */
    protected $xml;

    /**
     * @param string $xml
     * @return self
     */
    public function setXml($xml)
    {
        $this->xml = $xml;
        return $this;
    }

    /**
     * @return string
     */
    public function getXml()
    {
        return $this->xml;
    }

    /** @var string */
    protected $icon;

    /**
     * @param string $icon
     * @return self
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /** @var string */
    protected $launchType;

    /**
     * @param string $launchType
     * @return self
     */
    public function setLaunchType($launchType)
    {
        $this->launchType = $launchType;
        return $this;
    }

    /**
     * @return string
     */
    public function getLaunchType()
    {
        return $this->launchType;
    }
}
