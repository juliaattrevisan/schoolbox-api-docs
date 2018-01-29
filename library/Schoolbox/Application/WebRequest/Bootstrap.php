<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\WebRequest;

use Alaress\Csrf\HeaderCheck;
use Alaress\DebugBar\DataCollector\RouteCollector;
use Alaress\DebugBar\DataCollector\TidewaysCollector;
use Alaress\DebugBar\DataCollector\XhprofCollector;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\Twig\Method as TwigMethod;
use Alaress\Redis;
use Aura\Di;
use Autoloader;
use Config;
use Database;
use Psr\Log\NullLogger;
use Schoolbox\Application\AbstractBootstrap;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;


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
        // Set up the debug bar, and add listeners for various parts of the debug bar
        // (database calls, XHProf)
        // Should go in a DI container instead of a static class.
        $this->setDebugEnabled(DebugBar::isEnabled());
        if($this->isDebugEnabled()) {
            DebugBar::init();
        }

        // Install into database
        if($this->isDebugEnabled()) {
            $debug_bar = DebugBar::getInstance();

            // Throw away existing connection and reconnect with persistence off
            $database = Database::getInstance();
            $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true,]);
            $debug_bar->addCollector($database->getCollector());
        }

        // XHProf on the debug bar.
        if ($this->isDebugEnabled() && Config::get('debug_xhprof')) {
            if (extension_loaded('xhprof')) {
                DebugBar::getInstance()->addCollector(new XhprofCollector());
            } elseif (extension_loaded('tideways')) {
                DebugBar::getInstance()->addCollector(new TidewaysCollector());
            }
        }
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
        parent::installDependencies();

        $di = $this->getKernel()->getDependencyInjector();

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

        // Initialise module configuration
        require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
        require_once SITE_root . '/conf/ModuleLoader.php';

        $customModuleLocation = sprintf(
            '/conf/modules/Custom/%s/',
            ucfirst($this->getKernel()->getDependencyInjector()->get('config')->get('site_location'))
        );
        $modulePaths = array_merge(
            glob(SITE_root . '/conf/modules/*.php'),
            glob(SITE_root . $customModuleLocation . '*.php')
        );

        foreach ($modulePaths as $routePath) {
            require_once $routePath;

            // Get a route name out of this path
            $path = preg_replace('/^'.preg_quote(SITE_root.'/conf/modules/', '/').'/', '', $routePath);
            $path = preg_replace('/\\.php$/', '', $path);
            $path = preg_replace('/\\//', '\\', $path);

            $class = 'Config\\Modules\\' . $path;

            if(class_exists($class, false)) {
                /** @var Config\Modules\ModuleConfigInterface $obj */
                $obj = new $class();
                $obj->configure($this->getKernel());
            }

        }

    }


    /**
     * Web requests require CSRF protection: that belongs in middleware.
     */
    public function runMiddleware()
    {
        // Ghetto CSRF middleware. Reject request if:
        // * It's not a GET or HEAD request
        // ** Disregard X-Http-Method-Override, as that can be set by client
        // * It's not a deliberately allowed request, API endpoint or vhost
        // ** In general: any request that authenticates via token instead of
        //    via session should be allowed
        // * Its headers don't match
        // Accept request if it somehow doesn't have a REQUEST_METHOD at all;
        // AFAIK this isn't fakeable, and this gets called by some CLI scripts
        // despite it being the WebRequest bootstrap. (Go figure.)

        $scriptWhitelist = [ '*/dav/index.php' ];
        foreach ($scriptWhitelist as $path) {
            if (fnmatch($path, $_SERVER['SCRIPT_FILENAME'])) {
                return;
            }
        }

        if (isset($_SERVER['REQUEST_METHOD']) && !\in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {

            // Check against whitelist first
            $whitelist = ['/saml/consume.php*', '/api/register/*', '/loginForm.php*', '/liss/liss.php' ];
            foreach ($whitelist as $path) {
                if (fnmatch($path, $_SERVER['REQUEST_URI'])) {
                    return;
                }
            }

            // Then verify request origin
            /** @var IConfigLoader $config */
            $di        = $this->getKernel()->getDependencyInjector();
            $config    = $di->get('config');
            $fqdn      = trim($config->get('site_hostname').".".$config->get('site_domain'), ';.');
            $csrfCheck = new HeaderCheck($_SERVER, $fqdn);
            if (!$csrfCheck->verifyRequestOrigin()) {
                // Let's just say 403 Forbidden for now
                // TODO replace the call to Sitepage::error() method
                /** @var \SitePage $page */
                $page = $di->get('layoutEngine');
                $page->error(ErrorMapper::ERROR_FORM_SUBMIT, 'Invalid Request Origin', 403);
            }
        }
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
