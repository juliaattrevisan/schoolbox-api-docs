<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

/**
 * How should the `aud` claim be verified for JWTs issued by a Schoolbox
 * instance?
 */
enum AudienceMatchMode
{
    /**
     * If a JWT's audience is not set, or does not match the instance's
     * hostname: log a warning, but otherwise do not reject the JWT.
     *
     * Intended as a transitional state between not verifying the audience at
     * all, and strictly verifying the audience.
     *
     * Will be required for some cases, e.g.:
     * * login codes generated prior to 26.1
     * * logging into another instance from an instance prior to 26.1
     */
    case WARN;

    /**
     * If a JWT's audience is not set, or does not match the instance's
     * hostname: reject the JWT.
     */
    case REJECT;
}
