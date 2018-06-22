<?php
/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\SHAPS\Auth;


class Digest
{
    /**
     * @var string
     */
    private $schoolCode;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * AuthDigest constructor.
     * @param string $schoolCode
     * @param string $accessToken
     */
    public function __construct(string $schoolCode, string $accessToken)
    {
        $this->schoolCode = $schoolCode;
        $this->accessToken = $accessToken;
    }

    /**
     * Generate a digest from a timestamp, schoolcode and access token
     *
     * @param int $timestamp
     * @return string
     */
    public function generate(int $timestamp) : string
    {
        return sha1(sprintf(':%s-%s-%s:', $timestamp, $this->schoolCode, $this->accessToken));
    }
}