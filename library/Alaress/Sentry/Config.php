<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Alaress\Authorizer\Failure\Base as BaseAuthorizationFailure;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Instance;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\User;
use Sentry;

/**
 * Configuration helper functions for the Sentry hub.
 */
class Config
{
    /**
     * Only these types of error are reported to Sentry.
     */
    private const ERROR_REPORTING = E_ALL & ~E_NOTICE & ~E_USER_NOTICE;

    /**
     * Deprecated default sentry DSN, for when `$sentryDsn` isn't provided to
     * `init`.
     */
    private const DEFAULT_SENTRY_DSN = "https://%s@sentry.io/%s";

    /**
     * Initialise the Sentry hub as much as it can be before the instance's
     * configuration is known.
     *
     * @param string|null $sentryDsn         The URL to send Sentry events to.
     * @param float|null  $errorSampleRate   The rate at which to sample errors (1: all, 0: none).
     * @param float|null  $traceSampleRate   The rate at which to sample performance traces (1: all, 0: none).
     * @param float|null  $profileSampleRate The rate at which to sample profiles (1: all, 0: none).
     *                                       Note that this is relative to `$traceSampleRate`, as profiles are only
     *                                       sampled if the trace is.
     * @param string|null $sentryToken       Deprecated parameter for the token component of the Sentry DSN:
     *                                       only used if `$sentryDsn` isn't set.
     * @param string|null $sentryProject     Deprecated parameter for the project component of the Sentry DSN:
     *                                       only used if `$sentryDsn` isn't set.
     * @return Sentry\State\HubInterface
     */
    public static function init(
        ?string $sentryDsn,
        ?float $errorSampleRate,
        ?float $traceSampleRate,
        ?float $profileSampleRate,
        ?string $sentryToken = null,
        ?string $sentryProject = null,
    ): Sentry\State\HubInterface {
        // DSN not set: construct from token and project
        if (!isset($sentryDsn) || $sentryDsn === '') {
            if (isset($sentryToken, $sentryProject) && $sentryToken !== '' && $sentryProject !== '') {
                $sentryDsn = sprintf(self::DEFAULT_SENTRY_DSN, $sentryToken, $sentryProject);
            }
        }

        // DSN still not set? Don't use Sentry
        if (!isset($sentryDsn) || $sentryDsn === '') {
            $hub = new NullHub();
            Sentry\SentrySdk::setCurrentHub($hub);
            return $hub;
        }

        Sentry\init([
            'dsn'                  => $sentryDsn,
            'before_send'          => new CompositeFilter(
                new ExceptionFilter(
                    Deleted::class,
                    NotFound::class,
                    BaseAuthorizationFailure::class,
                    SchoolboxException::class
                ),
                new DeprecationFilter(),
            ),
            'error_types'          => self::ERROR_REPORTING,
            'send_default_pii'     => true,
            'attach_stacktrace'    => true,
            'sample_rate'          => $errorSampleRate,
            'traces_sample_rate'   => $traceSampleRate,
            'profiles_sample_rate' => $profileSampleRate,
        ]);

        return Sentry\SentrySdk::getCurrentHub();
    }

    /**
     * Configure the Sentry hub with details from the instance.
     *
     * @param Instance $instance
     * @return void
     */
    public static function useInstance(Instance $instance): void
    {
        Sentry\configureScope(static function (Sentry\State\Scope $scope) use ($instance): void {
            $scope->setTags([
                'instance_id'      => $instance->getId(),
                'php_version'      => PHP_VERSION,
                'site_environment' => $instance->getSiteEnvironment(),
                'site_version'     => $instance->getVersion(),
                'site_school_code' => $instance->getSchoolCode(),
            ]);
        });

        Sentry\SentrySdk::getCurrentHub()->getClient()?->getOptions()
            ?->setEnvironment($instance->getType())
            ?->setRelease(Instance::APPLICATION_NAME . '@' . $instance->getVersion())
        ;
    }


    /**
     * Configure the Sentry hub with the current session identifier.
     *
     * @param string|null $sessionIdentifier
     * @return void
     */
    public static function useSessionIdentifier(?string $sessionIdentifier): void
    {
        Sentry\configureScope(static function (Sentry\State\Scope $scope) use ($sessionIdentifier): void {
            /* Logout leads to this possibility
             */
            if (null !== $sessionIdentifier) {
                $scope->setUser([
                    'id' => $sessionIdentifier,
                ]);
            }
        });
    }
}
