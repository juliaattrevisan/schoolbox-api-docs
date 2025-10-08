<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Deprecation;

/**
 * A registry of known paths to classes which should be treated specially when
 * they raise deprecation warnings.
 *
 * Paths in neither of `PATH_BLACKLIST` or `PATH_WHITELIST` will raise
 * deprecation warnings as per normal.
 */
class Registry
{
    /**
     * If a class in any of these paths raises a deprecation warning: it will
     * never be silenced, and will always be reported.
     *
     * This takes precedence over `PATH_BLACKLIST`: for now, all classes written
     * by Schoolbox should be whitelisted.
     */
    public const PATH_WHITELIST = [
        SITE_root . '/library/',
        SITE_root . '/vendor/Aura/Router',
    ];

    /**
     * If a class in any of these paths raises a deprecation warning: it will
     * be silenced.
     *
     * As vendor libraries are updated to no longer raise deprecation warnings,
     * they should be added to `PATH_WHITELIST`.
     */
    public const PATH_BLACKLIST = [
        SITE_root . '/vendor/cboden/ratchet/',
        SITE_root . '/vendor/ratchet/rfc6455/',
    ];

    /**
     * Helper method for checking whether a deprecation warning raised from a
     * file in `$path` should be silenced or not.
     *
     * Note that the callback passed to `set_error_handler` is to return `false`
     * if it is to fall through to PHP's standard error handler.
     *
     * @param string $path The file from which the deprecation warning was
     *                     raised.
     * @return bool
     */
    public static function silenceWarningsForPath(string $path): bool
    {
        foreach (Registry::PATH_WHITELIST as $whitelistedPath) {
            if (str_contains($path, $whitelistedPath)) {
                return false;
            }
        }

        foreach (Registry::PATH_BLACKLIST as $blacklistedPath) {
            if (str_contains($path, $blacklistedPath)) {
                return true;
            }
        }
        return false;
    }
}
