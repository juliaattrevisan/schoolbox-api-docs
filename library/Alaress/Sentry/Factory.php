<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Alaress\Authorizer\Failure\Base as BaseAuthorizationFailure;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Sentry;

/**
 * Factory for building the Sentry reporter.
 */
class Factory
{
    /**
     * Only these types of error are reported to Sentry.
     */
    private const ERROR_REPORTING = E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_STRICT;

    /**
     * Build the Sentry reporter: if either the token or project are empty,
     * return a stub which reports nowhere, otherwise builds a live reporter.
     *
     * Also installs the error, exception and fatal error handler integrations
     * into the live reporter.
     *
     * @param Instance    $instance
     * @param string      $applicationName
     * @param string|null $sentryToken
     * @param string|null $sentryProject
     * @return Sentry\State\HubInterface
     */
    public function build(
        Instance $instance,
        string $applicationName,
        ?string $sentryToken,
        ?string $sentryProject,
        IConfigLoader $configLoader,
    ): Sentry\State\HubInterface {
        if (!isset($sentryToken, $sentryProject) || $sentryToken === '' || $sentryProject === '') {
            return new NullHub();
        }

        Sentry\init([
            'dsn'               => "https://{$sentryToken}@sentry.io/{$sentryProject}",
            'environment'       => $instance->getType(),
            'release'           => $applicationName . '@' . $instance->getVersion(),
            'before_send'       => new CompositeFilter(
                new ExceptionFilter(
                    Deleted::class,
                    NotFound::class,
                    BaseAuthorizationFailure::class,
                    SchoolboxException::class
                ),
                new DeprecationFilter(),
            ),
            'error_types'       => self::ERROR_REPORTING,
            'send_default_pii'  => true,
            'attach_stacktrace' => true,
            'traces_sampler'    => function (Sentry\Tracing\SamplingContext $context) use ($configLoader): float {
                // Do something with sample rates
                $lines = explode("\n", $configLoader->get(ConfigMapper::NAME_SENTRY_TRACE_CUSTOM_SAMPLE_RATES));
                foreach ($lines as $line) {
                    $row = str_getcsv($line);
                    if (count($row) >= 2) {
                        [$name, $sampleRate] = $row;
                        if (fnmatch($name, $context->getTransactionContext()?->getName())) {
                            return floatval($sampleRate);
                        }
                    }
                }
                return floatval($configLoader->get(ConfigMapper::NAME_SENTRY_TRACE_DEFAULT_SAMPLE_RATE));
            },
        ]);
        Sentry\configureScope(static function (Sentry\State\Scope $scope) use ($instance): void {
            $scope->setTags([
                'instance_id'      => $instance->getId(),
                'php_version'      => PHP_VERSION,
                'site_environment' => $instance->getSiteEnvironment(),
                'site_version'     => $instance->getVersion(),
                'site_school_code' => $instance->getSchoolCode(),
            ]);
        });

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
}
