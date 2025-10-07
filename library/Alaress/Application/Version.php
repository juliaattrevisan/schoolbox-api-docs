<?php

namespace Alaress\Application;

/**
 * Figure out the major version from the site version set in config.
 *
 * Note that schoolbox versions have a particular format such as:
 *
 *     26.0.alpha1
 *         - 26 is the year (short for 2026)
 *         - 0 is the major version
 *         - alpha1 is the minor version
 *
 *     26.1.8
 *         - 26 is the year (short for 2026)
 *         - 1 is the major version
 *         - 8 is the minor version
 *
 * These config versions are actually derived from a directory parse in /updates.
 * The names of these folders eventually become $siteVersion. This is set in update-sql.sh
 * and isn't particularly obvious.
 *
 * This throws a DomainException when you've got an incorrectly-formatted version in your
 * updates folder which can happen by human error.
 */
class Version
{
    /**
     * Generic error message prompting devs to check the /updates folder for a malformed directory name
     */
    private const MESSAGE = 'Invalid site version: check updates folder for a malformed name.';


    /**
     * Figure out the year+major version combination from the $siteVersion value
     *
     * @param string|null $siteVersion
     * @return string
     */
    public function generate(?string $siteVersion): string
    {
        preg_match('/^(\d+\.\d+)\.(\w+)$/', $siteVersion, $matches);

        /* Non-existent: invalid
         */
        if (!isset($matches[1])) {
            throw new \DomainException(self::MESSAGE);
        }

        /* Empty string: also invalid
         */
        if ('' === trim($matches[1])) {
            throw new \DomainException(self::MESSAGE);
        }

        return $matches[1];
    }
}