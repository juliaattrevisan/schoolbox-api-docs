<?php
/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SHAPS;


class HelpToken
{
    /**
     * @var string
     */
    private $schoolCode;


    /**
     * @var string
     */
    private $privateKey;

    /**
     * HelpToken constructor.
     * @param string $schoolCode
     * @param string $privateKey
     */
    public function __construct(string $schoolCode, string $privateKey) {
        $this->schoolCode = $schoolCode;
        $this->privateKey = $privateKey;
    }

    /**
     * Generate a token from a timestamp
     *
     * @param $timestamp
     * @return string
     */
    public function generate($timestamp) {
        return sha1(sprintf(':%s-%s-%s:', $timestamp, $this->schoolCode, $this->privateKey));
    }
}