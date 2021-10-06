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

/**
 * An LTI provider.
 *
 * As of LTI 1.3, this is known as a tool.
 */
class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;
    use CreatedAtDate;
    use UpdatedAtDate;

    /**
     * LTI version 1.1
     */
    public const LTI_VERSION_1_1 = '1.1';

    /**
     * LTI version 1.3
     */
    public const LTI_VERSION_1_3 = '1.3';

    /**
     * Available LTI versions
     */
    public const LTI_VERSIONS = [self::LTI_VERSION_1_1, self::LTI_VERSION_1_3];

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

    /**
     * Which LTI version does this provider use?
     *
     * Must be in `self::LTI_VERSIONS`.
     */
    private string $ltiVersion;

    /**
     * LTI 1.3: the audience of this tool.
     *
     * This will be provided by the tool, and will be:
     * * the `iss` claim in any JWTs sent from the tool to Schoolbox
     * * tge `aud` claim in any JWTs sent from Schoolbox to the tool
     *
     * These claims must match the tool's issuer in order for JWTs to verify.
     */
    private ?string $audience;

    /**
     * LTI 1.3: the OIDC initiation URL of this tool, required for
     * authentication of the Schoolbox user into the tool.
     */
    private ?string $oidcInitiationUrl;

    /**
     * LTI 1.3: the default launch URL of this tool, will be used as the default
     * target link URL for resource link launch requests.
     *
     * Might be optional? If this tool is a Basic launch type, then this is the
     * only relevant URL.
     */
    private ?string $launchUrl;

    /**
     * LTI 1.3: the deep linking URL of this tool, will be used as the launch
     * URL for deep linking requests.
     *
     * (Deep linking requests will return a URL which may be used in a resource
     *  link launch request.)
     *
     * Optional? If this tool is a Picker launch type, then this is the only
     * relevant URL.
     */
    private ?string $deepLinkingUrl;

    // LTI 1.1 fields

    /** @var string */
    protected $name;

    /** @var string */
    protected $custom;

    /** @var string */
    protected $key;

    /** @var string */
    protected $secret;

    /** @var string */
    protected $url;

    /** @var string */
    protected $xml;

    /** @var string */
    protected $icon;

    /** @var string */
    protected $launchType;

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
