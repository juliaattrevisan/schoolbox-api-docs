<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json\Api;

interface Link
{
    /**
     * Return this link's href
     *
     * @return string
     */
    public function getHref(): string;


    /**
     * Return whether or not the current session user is allowed to access this link.
     * Make sure you return NULL if the answer is "I don't know"
     *
     * @return bool|null
     */
    public function getAuth(): ?bool;
}
