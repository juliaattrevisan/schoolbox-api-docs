<?php
/**
 * This kernel is a stripped down version of the full web request kernel.  It is designed for use when there is
 * no user interface to display.  For example streaming files, images and videos.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Application\BasicRequest;

use Alaress\Csrf\HeaderCheck;
use Aura\Di;
use Autoloader;
use Config;
use Psr\Log\NullLogger;
use \Database;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Database\Factory as SchoolboxDatabaseFactory;
use Alaress\Database\Factory as AlaressDatabaseFactory;
use Alaress\Session\Factory;
use Alaress\Redis;
use Schoolbox\Instance;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Application\AbstractBootstrap;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Application\WebRequest\ShutdownHandler;

class Bootstrap extends AbstractBootstrap {

    /**
     * Load the core configuration file(s)
     *
     * @return void
     */
    public function loadConfiguration() {
        require_once __DIR__ . '/../../../../www/conf/conf.php';
        require_once SITE_root . '/www/include/stdfuncs.php';
        require_once SITE_root . '/library/constants.php';
    }


    /**
     * Set the include path
     *
     * @return void
     */
    public function setIncludePath() {
        set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
    }


    /**
     * Register any autoloading required
     *
     * @return void
     */
    public function registerAutoLoading() {
        require_once 'library/Autoloader.php';
        Autoloader::initialise();
    }


    /**
     * Initialise debugging
     *
     * @return void
     */
    public function initialiseDebugging() {
    }


    /**
     * Register any error handling required
     *
     * @return void
     */
    public function registerErrorHandling() {
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
     * Register any out-the-top exception handling required
     *
     * @return void
     */
    public function registerExceptionHandling() {
        set_exception_handler([$this, 'shutdownSafely']);
    }


    /**
     * Register any shutdown handling required
     *
     * @return void
     */
    public function registerShutdownHandling() {
        ShutdownHandler::instance()->registerFunction([$this, 'shutdownSafely']);
    }


    /**
     * Initialise connections to databases, etc.
     *
     * @return void
     */
    public function initialiseExternalConnections() {
        $database = Database::getInstance();
        // Catch-22:
        // I don't know if debug is enabled until I connect to the database.
        // The only solution is to allow the config query to run and then throw
        // away my connection, and create a fresh one with debug enabled.
        $database->connect(SQL_dsn, SQL_user, SQL_passwd);
    }


    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * @return void
     */
    public function installDependencies()
    {
        // Set up the dependency injector
        $di = new Di\Container(new Di\Factory());

        // Config loader
        $di->set('config', $di->lazyNew(ConfigLoader::class));

        Redis::getInstance()->connect($di->get('config')->get('redis_url'), null, INSTANCE_ID);
        $di->set('redis', Redis::getClientInstance());

        $di->set('amqp', $di->lazy(function () {
            return Connector::getInstance();
        }));

        $di->set('instance', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $conf */
            $conf = $di->get('config');
            return new Instance(
                INSTANCE_ID,
                $conf->get('site_location'),
                $conf->get('site_hostname'),
                $conf->get('site_domain'),
                $conf->get('site_version'),
                $conf->get('site_type'),
                $conf->get('site_school_code')
            );
        }));

        // install mapper DI
        // (yes I know this is icky, but it will have to do until mapper construction is set up with DI properly)
        Mapper::di($di);

        // Set correct timezone
        date_default_timezone_set($timezone = $di->get('config')->get('date_timezone'));

        // Notification factory
        $di->set('notif',
            $di->lazy(
                function() {
                    // replace this with $di->lazyGetCall() once we upgrade Aura.Di
                    return Notifactory::create();
                }
            )
        );

        // Database connection factory
        $di->set(
            'databaseFactory',
            function() use ($di) {
                return new SchoolboxDatabaseFactory(
                    new AlaressDatabaseFactory()
                );
            }
        );

        // Schoolbox internal database connection
        $di->set(
            'databaseInternal',
            function() use ($di) {
                /** @var SchoolboxDatabaseFactory $factory */
                $factory = $di->get('databaseFactory');
                return $factory->getInternalDatabase();
            }
        );

        // External SIS database connection(s)
        $di->set(
            'databaseExternal',
            $di->lazy(
                function() use ($di) {
                    /** @var SchoolboxDatabaseFactory $factory */
                    $factory = $di->get('databaseFactory');

                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    return $factory->getExternalDatabase($config);
                }
            )
        );

        // Session
        $di->set(
            'session',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    $factory = new Factory(
                        $_COOKIE,
                        $config,
                        $di->get('redis'),
                        $_SERVER['REQUEST_TIME']
                    );
                    $session = $factory->build();
                    return $session;
                }
            )
        );

        // User from session
        $di->set(
            'user',
            $di->lazy(
                function () use ($di) {
                    $session = $di->get('session');
                    return $session->getAuthUser();
                }
            )
        );

        $di->set(
            'l10n',
            $di->lazy(
                function () use ($di) {
                    $stoneFactory = new RosettaStoneFactory();
                    return $stoneFactory->build($di->get('mapperFactory')->get(L10nMapper::class));
                }
            )
        );

        // Enable mappers to be injectable, but instantiated only when required
        $mapperFactory = new MapperFactory($di);
        $di->set('mapperFactory', $mapperFactory);

        // register an empty logger to maintain interfaces as required
        $di->values['logger'] = new NullLogger();

        // Mapper notifications:
        // They rely upon the session being injected into them, so do that.
        $di->setters['Schoolbox\\Mapper']['setSessionData'] = $di->lazy(function() use($di) {
            /** @var \Alaress\Session\Session $session */
            $session = $di->get('session');
            $session->start();
            return isset($_SESSION) ? $_SESSION : array();
        });

        // initialise session
        $di->get('session')->start();


    }


    /**
     * Web requests require CSRF protection: that belongs in middleware.
     */
    public function runMiddleware()
    {
    }


    /**
     * Shutdown handler for either exceptions or fatal errors
     *
     * @param \Exception|array $e
     * @param array $stack
     */
    public function shutdownSafely($e = null, $stack = null)
    {
        $outputStackTrace = Config::get('debug_show_stack_trace');


        if ($e instanceof \Exception || $e instanceof \Throwable) {
            $error = array(
                'file'    => $e->getFile(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'type'    => $e->getCode(),
            );

            $this->getKernel()->callExceptionCallbacks($e);

        } elseif (is_array($e)) {
            $error = $e;
        } else {
            $error = error_get_last();
        }

        // it's almost a guarantee that some kind of minor error will have occurred at some point
        if ($error !== null) {

            // use the document_root to get a Schoolbox-relative file in which the error occurred
            $cause = substr($error['file'], strlen($_SERVER['DOCUMENT_ROOT']) - 4);

            // if it was a fatal error or exception that triggered our sudden but inevitable demise...
            if (
                (intval($error['type']) & (E_ERROR | E_CORE_ERROR | E_USER_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR))
                || $e instanceof \Exception || $e instanceof \Throwable
            ) {
                // RedisException extends RuntimeException: occurs when Redis
                // session management isn't working for whatever reason.
                $code    = 400;
                $info    = 'If the problem persists, please contact your System Administrator including the following information:';

                if ($e instanceof \PDOException){
                    $code = 500;
                    $message = 'Schoolbox is currently experiencing internal problems with its database. This issue has been logged.';
                } else if ($e instanceof \RuntimeException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently down due to an internal service being inactive.';
                } else {
                    $message = 'Schoolbox has encountered a fatal error.';

                    // decide what to tell the user based on where this error came from
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

                // Report to New Relic
                if (extension_loaded('newrelic')) {
                    if ($e instanceof \Exception || $e instanceof \Throwable) {
                        newrelic_notice_error(null, $e);
                    }
                }

                if(isset($_SERVER['HTTP_HOST'])) {
                    $url = 'http'.(getenv("SERVER_PORT") == 443 ? 's://' : '://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                }
                else {
                    $url = '';
                }

                // explain that something went cataclysmically wrong.

                // Use Sitepage to output an error message in the school's design.
                // If there's no SitePage in DI yet: just output a styleless error.
                $di = $this->getKernel()->getDependencyInjector();

                if (!$e instanceof \Exception && !$e instanceof \Throwable) {
                    $e = $error;
                    $e['code'] = $code;

                    if (is_array($stack)) {
                        array_shift($stack);
                        $e['trace'] = $stack;
                    }
                }

                /* Use structured information for rendering
                 */
                $args = [
                    'message' => $message,
                    'info' => $info,
                    'url' => $url,
                    'error' => $error,
                    'date' => date('Y-m-d H:i:s'),
                    'outputStackTrace' => $outputStackTrace,
                    'stackTrace' => $e
                ];

                /* Pass $data straight in for twig rendering
                 */
                if ($di instanceof Di\Container) {
                    /** @var \SitePage $page */
                    $page = $di->get('layoutEngine');
                    $page->error(null, $args, $code);
                }
                /* Just dump it out in raw form
                 * TODO: Make synonymous with twig output
                 */
                else {
                    $output =
                        "<p><b>{$args['message']} {$args['info']}</b></p>" .
                        '<ul>' .
                        "<li>Datetime: {$args['date']}</li>" .
                        "<li>URL: {$args['url']}" .
                        '</li>';

                    if ($outputStackTrace) {
                        $output .= "<li>Error: {$args['error']['message']} in {$args['error']['file']} on line {$args['error']['line']}.</li>";
                    } else {
                        $output .= "<li>Error: {$args['error']['message']}. (Further details have been logged.)</li>";
                    }

                    $output .= '</ul>';

                    echo $output;
                }
            }
        }
    }
}
