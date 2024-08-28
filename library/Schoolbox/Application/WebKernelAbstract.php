<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application;

use Alaress\Application\WrongSapiException;
use Alaress\Session\Session;
use Alaress\Web\Response\Factory as ResponseFactory;
use Alaress\Web\Response\Outputter;
use InvalidArgumentException;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\API\Token\JwtParser;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Aura\Di;
use Schoolbox\Application\Exception\NotAuthenticated as SchoolboxNotAuthenticatedException;
use Schoolbox\Deprecation\Registry;
use Schoolbox\IConfigLoader;
use Alaress\Web\Request\IsJson;
use Schoolbox\User;
use Throwable;

/**
 * Common ancestor for all Web Requests
 */
abstract class WebKernelAbstract extends \Alaress\Application\Kernel
{
    /**
     * Output a "Cache-Control" response header.
     *
     * This instructs the browser to do the following, depending on `$maxAge`:
     * * > 0: cache (and not re-request) this response until it is `$maxAge`
     *  seconds old
     * * <= 0: not cache this response at all
     *
     * Also removes the prehistoric "Pragma" and "Expires" headers, as these
     * conflict with "Cache-Control".
     *
     * @param int $maxAge The number of seconds to instruct the browser to cache
     *                    this response for.
     */
    public function outputCacheControlMaxAgeHeader(int $maxAge): void
    {
        if ($maxAge <= 0) {
            header('Cache-Control: no-cache, private');
        } else {
            header(sprintf('Cache-Control: max-age=%d, private', $maxAge));
        }
        header_remove('Pragma');
        header_remove('expires');
    }


    /**
     * Output a "Cache-Control: max-age" response header, for an amount of time
     * based on a config value.
     *
     * @see outputCacheControlMaxAgeHeader
     * @param string $configName Set the max age to the value of this config.
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function outputCacheControlMaxAgeHeaderFromConfig(string $configName): void
    {
        /** @var IConfigLoader $config */
        /** @noinspection PhpUnhandledExceptionInspection */
        $config = $this->get('config');
        $maxAge = $config->get($configName);
        $this->outputCacheControlMaxAgeHeader((int)$maxAge);
    }


    /**
     * Output a header appropriate for indicating that this resource is still
     * being processed.
     *
     * It's not appropriate to instruct the browser to cache responses for
     * requests for resources that are still processing (at least not for
     * nearly as long as standard redirects).
     *
     * For now, this sets the cache max age to zero so the redirect which would
     * be generated is not cached at all.
     */
    public function outputResourceBeingProcessedHeader(): void
    {
        $this->outputCacheControlMaxAgeHeader(0);
    }


    /**
     * Shutdown handler for either exceptions or fatal errors
     *
     * @param \Exception|array $e
     * @param array            $stack
     * @return bool
     */
    public function shutdownSafely($e = null, $stack = null): bool
    {
        $isException = false;
        if ($e instanceof Throwable) {
            $isException = true;

            // Special case for oAuth exceptions:
            // they provide a hint about what might've gone wrong
            $message = $e->getMessage();
            if ($e instanceof OAuthServerException) {
                $message = sprintf('%s (Hint: %s)', $message, $e->getHint());
            }
            $error = [
                'file'    => $e->getFile(),
                'message' => $message,
                'line'    => $e->getLine(),
                'type'    => $e->getCode(),
            ];

            $this->callExceptionCallbacks($e);
        } elseif (\is_array($e)) {
            $error = $e;
        } else {
            $error = error_get_last();
        }

        // it's almost a guarantee that some kind of minor error will have occurred at some point
        if ($error !== null) {
            // if it was a fatal error or exception that triggered our sudden but inevitable demise...
            $errorType = ((int)$error['type']);
            if (
                $isException
                || ($errorType & (E_ERROR | E_CORE_ERROR | E_USER_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))
            ) {
                // RedisException extends RuntimeException: occurs when Redis
                // session management isn't working for whatever reason.
                $code = 400;
                $info = 'If the problem persists, please contact your System Administrator including the following information:';
                $infoFromLocation = false;

                if ($e instanceof SchoolboxException) {
                    $code    = $e->getHttpStatusCode();
                    $message = $e->getMessage();
                    $info    = '';
                } elseif ($e instanceof JwtParser\Exception\Unparseable) {
                    $code    = 401;
                    $message = 'Schoolbox could not parse your auth token.';
                    $info    = $e->getMessage();
                } elseif ($e instanceof JwtParser\Exception\Rejected) {
                    $code    = 401;
                    $message = 'Schoolbox has detected a problem with your auth token.';
                    $info    = $e->getMessage();
                } elseif ($e instanceof JwtParser\Exception\InvalidIdentity) {
                    $code    = 401;
                    $message = 'Schoolbox has detected that your auth token identifies an invalid user.';
                    $info    = $e->getMessage();
                } elseif (
                    // MySQL Query execution was interrupted - "70100"
                    // MsSQL Query timeout expired = "HYT00" and message indicates QUERY timeout rather than a LOGIN timeout (they share the same code)
                    ($e instanceof \PDOException && ($e->getCode() === "70100" || ($e->getCode() === "HYT00" && str_contains($e->getMessage(), "Query timeout")))) ||
                    ($e instanceof \Alaress\Database\Exception &&
                        $e->getPrevious() instanceof \PDOException &&
                        ($e->getPrevious()->getCode() === "70100" || ($e->getPrevious()->getCode() === "HYT00" && str_contains($e->getPrevious()->getMessage(), "Query timeout")))
                    )
                ) {
                    $code    = 599;
                    $message = "Unfortunately your query was taking too long. To avoid further disruption to the system we have stopped this request. Please try again later as this may be a temporary performance issue.";
                } elseif ($e instanceof \PDOException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently experiencing internal problems with its database. This issue has been logged.';
                } elseif ($e instanceof RuntimeException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently down due to an internal service being inactive.';
                } elseif (preg_match("/(Maximum execution time of \d+ seconds? exceeded)|(Allowed memory size of \d+ bytes? exhausted)/", $error['message'] ?? '')) {
                    ob_clean();
                    $code             = 504;
                    $message          = $error['message'];
                    $infoFromLocation = true;
                } else {
                    $info             = sprintf('%s %s', $info, $error['message']);
                    $message          = 'Schoolbox has encountered a fatal error.';
                    $infoFromLocation = true;
                }

                if ($infoFromLocation) {
                    // decide what to tell the user based on where this error came from
                    // use the document_root to get a Schoolbox-relative file in which the error occurred
                    $cause = substr($error['file'], \strlen($_SERVER['DOCUMENT_ROOT']) - 4);
                    switch ($cause) {
                            // certain queries in the reports can return too much data to fit under the memory limit
                        case '/library/Report/Login.php':
                        case '/library/Report/Activity.php':
                            $info = "The report you tried to run has returned too much data. Please press 'Back' and refine your report filter parameters.";
                            break;
                            // if it's not somewhere we expect to find an error, just be generic about it
                        default:
                            break;
                    }
                }

                // explain that something went cataclysmically wrong.
                if (!$isException) {
                    $e         = $error;
                    $e['code'] = $code;

                    if (\is_array($stack)) {
                        array_shift($stack);
                        $e['trace'] = $stack;
                    }
                }
                $this->outputError($e, $message, $info, $error, $code);
            } elseif ($errorType & (E_DEPRECATED | E_USER_DEPRECATED)) {
                // Deprecation notices:
                // silence (for now) if raised in a vendor library
                return Registry::silenceWarningsForPath($error['file']);
            }
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function registerErrorHandling(): void
    {
        // Temporarily handle deprecation warnings, so that they may be
        // temporarily ignored.
        // We cannot catch 'em all (yet).
        set_error_handler(
            function ($code, $message, $file, $line) {
                return $this->shutdownSafely(
                    [
                        'type'    => $code,
                        'message' => $message,
                        'file'    => $file,
                        'line'    => $line
                    ],
                    debug_backtrace(),
                );
            },
            E_USER_ERROR | E_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED,
        );
    }

    /**
     * @inheritdoc
     */
    protected function registerExceptionHandling(): void
    {
        set_exception_handler([$this, 'shutdownSafely']);
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    protected function registerShutdownHandling(): void
    {
        $this->getShutdownHandler()->registerFunction(
            new ResourceLimitExceededHandler(function (): void {
                $this->shutdownSafely(error_get_last(), debug_backtrace());
            })
        );
        register_shutdown_function($this->getShutdownHandler());
    }


    /**
     * @inheritdoc
     * @throws WrongSapiException
     */
    protected function checkServerApiEnvironment(): void
    {
        if (PHP_SAPI === 'cli') {
            throw new WrongSapiException('Cannot use Web Kernel in CLI environment.');
        }
    }


    /**
     * Initialise the session, in addition to running the parent class'
     * post-install dependencies.
     *
     * @noinspection PhpDocMissingThrowsInspection the session management object
     *                                             will always be in DI
     */
    protected function postInstallDependencies(bool $bypassMaintenanceModeCheck = false): void
    {
        parent::postInstallDependencies($bypassMaintenanceModeCheck); // Check for maintenance mode is done in here.

        // initialise session
        /** @var Session $session */
        /** @noinspection PhpUnhandledExceptionInspection */
        $session = $this->getDependencyInjector()->get('session');
        if (!$session->isStarted()) {
            $session->start();
        }
    }

    /** @inheritDoc */
    protected static function getKnownUserClassesFromSession(Di\Container $di): array
    {
        // Get known user-to-class mapping from the session and inject into factory
        $knownUserClasses = [];

        /** @var Session $session */
        $session = $di->get('session');

        /** @var User $user */
        $user = $session->get('user');

        // Self case
        $subjects = $session->get('subjects');
        if ($subjects) {
            $knownUserClasses[$user->getId()] = $subjects;
        }
        // Parent case
        foreach ($session->get('children', []) as $childData) {
            $knownUserClasses[$childData['id']] = $childData['subjects'];
        }
        return $knownUserClasses;
    }


    /**
     * Output an error. The format depends on the request type (JSON or HTML).
     *
     * @param array|Throwable $e        The throwable that led us here, or the
     *                                  stack trace in array form.
     * @param string          $message  A human-friendly message to output.
     * @param string          $info     More descriptive description of what
     *                                  happened.
     * @param array           $error    An array-format stack trace
     * @param int             $code     A HTTP status code
     */
    protected function outputError(
        $e,
        string $message,
        string $info,
        $error,
        int $code
    ): void {
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) || isset($_SERVER['HTTP_HOST'])) {
            $serverHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
            $url        = 'http' . (getenv("SERVER_PORT") === '443' ? 's://' : '://') . $serverHost . $_SERVER['REQUEST_URI'];
        } else {
            $url = '';
        }

        /* Use structured information for rendering
         */
        $args = [
            'message'        => $message,
            'info'           => $info,
            'url'            => $url,
            'error'          => $error,
            'date'           => \Alaress_Date_Format::now(),
            'stackTrace'     => $e,
            'exceptionClass' => \is_object($e) ? \get_class($e) : 'Error',
        ];

        /* Pass $data straight in for twig rendering
         */
        $di = $this->getDependencyInjector();
        if ($di instanceof Di\Container) {
            // Is it JSON or not?
            try {
                /** @var IConfigLoader $config */
                $config                   = $di->get('config');
                $args['outputStackTrace'] = $config->get('debug_show_stack_trace');

                /** @var ServerRequestInterface $request */
                $request = $di->get('httpRequest');

                /** @var ResponseFactory $responseFactory */
                $responseFactory = $this->di->get('httpResponseFactory');

                if ((new IsJson())->isSatisfiedBy($request)) {
                    $problem = (new ApiProblem($message))
                        ->setStatus($code)
                        ->setDetail($info)
                        ->setInstance($url);

                    $problem['message'] = $message;
                    if ($args['outputStackTrace']) {
                        $problem['trace'] = $e instanceof Throwable
                            ? $e->getTrace()
                            : ($e['trace'] ?? []);
                    }

                    $response = $responseFactory->buildJson($problem->asArray());
                } else {
                    $response = $responseFactory->buildHtml(
                        $code === 404 || $code === 410
                            ? 'common/error/404.twig'
                            : 'common/error/general.twig',
                        $args,
                        // Current user is not known here
                        null,
                        'Error Encountered',
                    );
                }

                // Finagle a response?
                /** @var Outputter $responseFactory */
                $responseOutputter = $this->di->get('httpResponseOutputter');
                $responseOutputter->output($response->withStatus($code));
                return;
            } catch (Di\Exception\ServiceNotFound $e) {
                // noop: fall thru to outputRawHtmlError
            }
        }

        // There was no DI, or I couldn't find a renderer.
        // Just output raw HTML
        $this->outputRawHtmlError($code, $args);
    }


    /**
     * Outputs an error without using a Twig or JSON renderer.
     *
     * This is a fallback that is used when none of those renderers are found.
     *
     * @param int   $code
     * @param array $args
     */
    private function outputRawHtmlError(int $code, array $args): void
    {
        /* Just dump it out in raw form
         * TODO: Make synonymous with twig output
         */
        $output =
            "<p><b>" . htmlentities("{$args['message']} {$args['info']}") . "</b></p>" .
            '<ul>' .
            "<li>Datetime: " . htmlentities($args['date']->getLong()) . "</li>" .
            "<li>URL: " . htmlentities($args['url']) .
            '</li>';

        if ($args['outputStackTrace']) {
            $output .= "<li>" . htmlentities("Error: {$args['error']['message']} in {$args['error']['file']} on line {$args['error']['line']}") . "</li>";
            if ($args['stackTrace'] instanceof Throwable) {
                foreach ($args['stackTrace']->getTrace() as $i => $frame) {
                    foreach (['class', 'type', 'function', 'file', 'line'] as $prop) {
                        $frame[$prop] ??= '';
                    }
                    $output .= <<<LINE
                    <li>
                        <pre>#$i:{$frame['class']}{$frame['type']}{$frame['function']}, called from {$frame['file']}:{$frame['line']}</pre>
                    </li>
                    LINE;
                }
            }
        } else {
            $output .= "<li>" . htmlentities("Error: {$args['error']['message']}. (Further details have been logged.)") . "</li>";
        }

        $output .= '</ul><a href="/">Return to home page</a> | <a href="javascript:history.back()">Go back</a>';

        http_response_code($code);
        echo $output;
    }


    /**
     * Extract a user from the "Authorization" HTTP header, if that header
     * exists.
     *
     * @param string|null $authHeader
     * @return array{?User, bool}       User (if found) + whether to IP track
     *                                  the session.
     * @throws JwtParser\Exception\Base if a header exists and is recognised,
     *                                  but is rejected for whatever reason
     */
    public function extractUserFromAuthHeader(?string $authHeader): array
    {
        if (isset($authHeader) && preg_match('/^Bearer\\s+(.*)$/i', $authHeader, $matches)) {
            [, $token] = $matches;

            /** @var JwtParser\IParser $tokenParser */
            $tokenParser = $this->get('user.jwtParser');

            if ($tokenParser->accepts($token)) {
                return [$tokenParser->determineUser($token), $tokenParser->bypassIpTracking()];
            }
        }
        return [null, false];
    }

    /**
     * Authenticate the user for this request only, storing their data in the
     * session.
     *
     * After this request finishes: restore the original session if somebody was
     * logged in, or destroy the session completely if nobody was logged in.
     *
     * @param User $user
     * @param bool $bypassIpTracking If true: bypass IP tracking of the session.
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws JwtParser\Exception\InvalidIdentity if the user could not be authenticated
     */
    public function authenticateUserForThisRequestOnly(User $user, bool $bypassIpTracking): void
    {
        // Block expired users right away
        if (!$user->getEnabled()) {
            throw new JwtParser\Exception\InvalidIdentity(
                'Schoolbox has detected that your auth token identifies an invalid user.',
            );
        }

        // Determine who was logged in, and preserve their data
        /** @var \Alaress\Session\Session $sessionDataStore */
        $sessionDataStore   = $this->di->get('session');
        $currentSessionData = null;
        $currentUser        = $sessionDataStore->getAuthUser();
        if ($currentUser->getId()) {
            $currentSessionData = $_SESSION;
        }

        // Restore or destroy the session when all is done
        $authenticator = new \Authenticator();
        $this->getShutdownHandler()->registerFunction(
            function () use ($authenticator, $currentSessionData): void {
                if (isset($currentSessionData)) {
                    $_SESSION = $currentSessionData;
                } else {
                    $authenticator->destroySession();
                }
            }
        );

        // Now authenticate the given user
        $authenticated = true;
        try {
            $authenticated = $authenticator->createSession($user->getUsername(), null, $bypassIpTracking);
        } catch (SchoolboxNotAuthenticatedException $e) {
            // noop: convert this unauthenticated error into another
        }
        if (!$authenticated) {
            throw new JwtParser\Exception\InvalidIdentity(
                'Schoolbox could not create your user session.',
                0,
                $e ?? null,
            );
        }

        // Also: override the DI user with this user?
        // Should not be required because the DI user is fetched from the
        // session, anyway.
        // (This would die when the container was locked, anyway)
        //        $this->di->set('user', $user);
    }
}
