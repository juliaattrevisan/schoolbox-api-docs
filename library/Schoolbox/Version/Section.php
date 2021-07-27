<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Version;

/**
 * Breakdown and make available sections of a schoolbox version string
 * @package Schoolbox\Version
 */
class Section
{
    /**
     * @var string the complete version string we want to inspect
     */
    protected string $fullVersion;

    public function __construct(string $fullVersion)
    {
        $this->fullVersion = $fullVersion;
    }

    /**
     * get a complete representation of this schoolbox instances version.
     *
     * @return string
     * @example these would look something like:
     *          - 21.0.2-1
     *          - 20.2.11-1
     *          - 20.1.8-2
     *          - 20.0.12-1
     *          - 19.1.12-3
     *          - 18.1.8-1
     *
     */
    public function getFull(): string
    {
        return $this->fullVersion;
    }

    /**
     * get the major version section of this running schoolbox instance.
     *
     * @return string
     * @example these would look something like:
     *          - 21.0
     *          - 20.2
     *          - 20.1
     *          - 20.0
     *          - 19.1
     *          - 18.1
     *
     */
    public function getMajor(): string
    {
        $majorVersion = "";
        /**
         * given the full version, everything leading up to the second
         * period is considered the major version
         */
        if (preg_match('/^(\d+\.\d+)\.*/', $this->getFull(), $matches)) {
            $majorVersion = $matches[1];
        }
        return $majorVersion;
    }

    /**
     * get the minor version section of this running schoolbox instance.
     *
     * @return string
     * @example these would look something like:
     *          - .4-1
     *          - .11-1
     *          - .8-2
     *
     */
    public function getMinor(): string
    {
        $minorVersion = '';
        /**
         * given the full version, everything after the second
         * period is defined as the minor version
         */
        if (preg_match('/^\d+\.\d+\.(.+)/', $this->getFull(), $matches)) {
            $minorVersion = $matches[1];
        }
        return $minorVersion;
    }
}
