<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Json\Api;

use Alaress\Json\Api\Link;

class SitePageLink implements Link
{
    /** @var string */
    private $href;

    /** @var bool|null */
    private $auth;


    /**
     * SitePageLink constructor.
     *
     * @param string    $href
     * @param bool|null $auth
     */
    public function __construct(string $href, bool $auth = null)
    {
        $this->href = $href;
        $this->auth = $auth;
    }


    /**
     * Return this link's href
     *
     * @return string
     */
    public function getHref(): string
    {
        return $this->href;
    }


    /**
     * Return whether or not the current session user is allowed to access this link.
     * Make sure you return NULL if the answer is "I don't know"
     *
     * @return bool|null
     */
    public function getAuth(): ?bool
    {
        return $this->auth;
    }
}
