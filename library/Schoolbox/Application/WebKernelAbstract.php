<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application;

use Alaress\Application\WrongSapiException;
use \Database;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Aura\Di;
use Schoolbox\IConfigLoader;
use Alaress\Web\Request\IsJson;
use Alaress\Web\Output;

/**
 * Common ancestor for all Web Requests
 */
abstract class WebKernelAbstract extends \Alaress\Application\Kernel
{

    /**
     * Shutdown handler for either exceptions or fatal errors
     *
     * @param \Exception|array $e
     * @param array            $stack
     */
    public function shutdownSafely($e = null, $stack = null): void
    {
        $isException = false;
        if ($e instanceof \Throwable) {
            $isException = true;

            $error = [
                'file'    => $e->getFile(),
                'message' => $e->getMessage(),
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
            if ($isException
                || ((int)$error['type'] & (E_ERROR | E_CORE_ERROR | E_USER_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))
            ) {
                // RedisException extends RuntimeException: occurs when Redis
                // session management isn't working for whatever reason.
                $code = 400;
                $info = 'If the problem persists, please contact your System Administrator including the following information:';

                if ($e instanceof SchoolboxException) {
                    $code    = $e->getHttpStatusCode();
                    $message = $e->getMessage();
                    $info    = '';
                } elseif ($e instanceof \PDOException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently experiencing internal problems with its database. This issue has been logged.';
                } elseif ($e instanceof \RuntimeException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently down due to an internal service being inactive.';
                } else {
                    $message = 'Schoolbox has encountered a fatal error.';

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
            }
        }
    }


    /**
     * @inheritdoc
     * @throws \Schoolbox\External\Exception
     */
    protected function initialiseExternalConnections(): void
    {
        $database = Database::getInstance();
        // Catch-22:
        // I don't know if debug is enabled until I connect to the database.
        // The only solution is to allow the config query to run and then throw
        // away my connection, and create a fresh one with debug enabled.
        $database->connect(SQL_dsn, SQL_user, SQL_passwd);
    }

    /**
     * @inheritdoc
     */
    protected function registerErrorHandling(): void
    {
        // noop. WebRequest does not use custom error handling
        set_error_handler(function ($code, $message, $file, $line) {
            $this->shutdownSafely([
                'type' => $code,
                'message' => $message,
                'file' => $file,
                'line' => $line
            ], debug_backtrace());
            return true;
        }, E_USER_ERROR | E_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR);
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
     */
    protected function registerShutdownHandling(): void {
        register_shutdown_function(array(ShutdownHandler::instance(), 'onShutdown'));
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
     * Output an error. The format depends on the request type (JSON or HTML).
     *
     * @param array|\Throwable $e       The throwable that led us here, or the
     *                                  stack trace in array form.
     * @param string           $message A human-friendly message to output.
     * @param string           $info    More descriptive description of what
     *                                  happened.
     * @param array            $error   An array-format stack trace
     * @param int              $code    A HTTP status code
     */
    protected function outputError(
        $e,
        string $message,
        string $info,
        $error,
        int $code
    ): void {

        if (isset($_SERVER['HTTP_HOST'])) {
            $url = 'http' . (getenv("SERVER_PORT") === '443' ? 's://' : '://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
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

                $request = $di->get('webFactory')->newRequest();
                if ((new IsJson())->isSatisfiedBy($request)) {
                    $problem = (new ApiProblem($message))
                        ->setStatus($code)
                        ->setDetail($info)
                        ->setInstance($url);

                    $problem['message'] = $message;
                    if ($args['outputStackTrace']) {
                        $problem['trace'] = array_merge(
                            [$error],
                            $e instanceof \Throwable ? $e->getTrace() : $e['trace']
                        );
                    }

                    /** @var Output\Json $output */
                    $output = $di->get('webOutput:json');
                    $output->setData($problem->asArray());
                } else {
                    /** @var Output\ThemedHtml $output */
                    $output = $di->get('webOutput:html');
                    $output
                        ->setTitle('Error Encountered')
                        ->setData($args)
                        ->setTemplate($code === 404 || $code === 410
                            ? 'common/error/404.twig'
                            : 'common/error/general.twig'
                        );
                }

                // Finagle a response?
                $response = $di->get('webFactory')->newResponse();
                $response->status->setCode($code);
                $output->output($response);
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
            "<p><b>{$args['message']} {$args['info']}</b></p>" .
            '<ul>' .
            "<li>Datetime: {$args['date']->getLong()}</li>" .
            "<li>URL: {$args['url']}" .
            '</li>';

        if ($args['outputStackTrace']) {
            $output .= "<li>Error: {$args['error']['message']} in {$args['error']['file']} on line {$args['error']['line']}.</li>";
        } else {
            $output .= "<li>Error: {$args['error']['message']}. (Further details have been logged.)</li>";
        }

        $output .= '</ul>';

        http_response_code($code);
        echo $output;
    }
}
