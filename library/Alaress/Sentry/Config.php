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
use Sentry;

/**
 * Configuration helper functions for the Sentry hub.
 */
class Config
{
    /**
     * Only these types of error are reported to Sentry.
     */
    private const ERROR_REPORTING = E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_STRICT;

    /**
     * Initialise the Sentry hub as much as it can be before the instance's
     * configuration is known.
     *
     * @param string|null $sentryDsn
     * @param float|null  $errorSampleRate
     * @param float|null  $traceSampleRate
     * @param float|null  $profileSampleRate
     * @return Sentry\State\HubInterface
     */
    public static function init(
        ?string $sentryDsn,
        ?float $errorSampleRate,
        ?float $traceSampleRate,
        ?float $profileSampleRate,
    ): Sentry\State\HubInterface {
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

        $hub = Sentry\SentrySdk::getCurrentHub();

        // The equivalent of the old Raven_ErrorHandler's register methods
        // (The transaction and request integrations are new)
        $integrationClasses = [
            Sentry\Integration\ErrorListenerIntegration::class,
            Sentry\Integration\ExceptionListenerIntegration::class,
            Sentry\Integration\FatalErrorListenerIntegration::class,
            Sentry\Integration\TransactionIntegration::class,
            Sentry\Integration\RequestIntegration::class,
        ];
        foreach ($integrationClasses as $integrationClass) {
            $integration = $hub->getIntegration($integrationClass);
            if ($integration) {
                $integration->setupOnce();
            }
        }

        return $hub;
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
}
