<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Schoolbox\Instance;
use Sentry;

/**
 * Class Factory
 */
class Factory
{
    /**
     * Build the Sentry reporter, if the token and project exist.
     *
     * Also installs the error, exception and fatal error handler integrations.
     *
     * @param Instance $instance
     * @param string   $sentryToken
     * @param string   $sentryProject
     * @return Sentry\State\HubInterface|null
     */
    public function build(Instance $instance, string $sentryToken, string $sentryProject): ?Sentry\State\HubInterface
    {
        if ($sentryToken !== '' && $sentryProject !== '') {
            Sentry\init([
                'dsn'         => "https://{$sentryToken}@sentry.io/{$sentryProject}",
                'environment' => $instance->getType(),
            ]);
            Sentry\configureScope(static function (Sentry\State\Scope $scope) use ($instance): void {
                // fall back to site_location if site_school_code not set
                $scope->setTags([
                    'php_version'      => PHP_VERSION,
                    'site_version'     => $instance->getVersion(),
                    'site_school_code' => $instance->getSchoolCode() ?: $instance->getLocation(),
                ]);
            });

            $hub = Sentry\SentrySdk::getCurrentHub();

            // The equivalent of the old Raven_ErrorHandler's register methods
            $errorListener = $hub->getIntegration(Sentry\Integration\ErrorListenerIntegration::class);
            if ($errorListener) {
                $errorListener->setupOnce();
            }

            $exceptionListener = $hub->getIntegration(Sentry\Integration\ExceptionListenerIntegration::class);
            if ($exceptionListener) {
                $exceptionListener->setupOnce();
            }

            $fatalErrorListener = $hub->getIntegration(Sentry\Integration\FatalErrorListenerIntegration::class);
            if ($fatalErrorListener) {
                $fatalErrorListener->setupOnce();
            }

            return $hub;
        }
        return null;
    }
}
