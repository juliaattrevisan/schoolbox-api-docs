<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Csrf;

/**
 * Middleware for CSRF protection.
 *
 * @todo make this PSR-15 compliant
 */
class Middleware
{
    /**
     * @var HeaderCheck
     */
    private $headerCheck;


    /**
     * Constructor
     *
     * @param HeaderCheck $headerCheck
     */
    public function __construct(HeaderCheck $headerCheck)
    {
        $this->headerCheck = $headerCheck;
    }


    /**
     * Should this request be blocked?
     *
     * @param array $serverVars        The content of the $_SERVER variable.
     * @param array $filenameWhitelist If the script filename matches one of these glob patterns, bypass check
     * @param array $uriWhitelist      If the URI matches one of these glob patterns, bypass check
     * @return bool
     */
    public function verify(
        array $serverVars,
        array $filenameWhitelist = [],
        array $uriWhitelist = []
    ): bool {
        foreach ($filenameWhitelist as $path) {
            if (fnmatch($path, $serverVars['SCRIPT_FILENAME'])) {
                return true;
            }
        }

        if (isset($serverVars['REQUEST_METHOD']) && !\in_array($serverVars['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
            // Check against whitelist first
            foreach ($uriWhitelist as $path) {
                if (fnmatch($path, $serverVars['REQUEST_URI'])) {
                    return true;
                }
            }

            // Then verify request origin
            if (!$this->headerCheck->verifyRequestOrigin()) {
                return false;
            }
        }
        return true;
    }
}
