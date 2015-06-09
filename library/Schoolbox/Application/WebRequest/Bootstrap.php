<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\WebRequest;


use Alaress\Application\Boot\BootstrapInterface;
use Alaress\DebugBar\DataCollector\XhprofCollector;
use Alaress\DebugBar\Instance as DebugBar;
use DebugBar\Bridge\Twig\TraceableTwigEnvironment;
use DebugBar\Bridge\Twig\TwigCollector;
use Alaress\Redis;
use Alaress\Session\Factory;
use Alaress\Web\Router\RouterFactory;
use Alaress_Date_Locale_Config;
use Aura\Di;
use Autoloader;
use Config;
use Database;
use DeprecatedDatabase;
use PDO;
use Schoolbox\L10n\RosettaStone;
use Scrub;
use Twig_Extension_Debug;
use Twig_Extensions_Extension_Text;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use Twig_SimpleFunction;

class Bootstrap implements BootstrapInterface {

	/** @var bool */
	protected $debugEnabled = false;

	/** @var Kernel */
	protected $kernel;

	/**
	 * @param Kernel $kernel
	 */
	public function __construct($kernel) {
		$this->kernel = $kernel;
	}


	/**
	 * Load the core configuration file(s)
	 *
	 * @return void
	 */
	public function loadConfiguration() {
		require_once __DIR__ . '/../../../../www/conf/conf.php';
		require_once SITE_root . '/www/include/stdfuncs.php';
		require_once SITE_root . '/include/constants.php';
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
		require_once 'include/Autoloader.php';
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
		if($this->debugEnabled = DebugBar::isEnabled()) {
			DebugBar::init();
		}

		// XHProf on the debug bar.
		if($this->debugEnabled && Config::get('debug_xhprof') && extension_loaded('xhprof')) {
			include_once '/usr/share/php5-xhprof/xhprof_lib/utils/xhprof_lib.php';
			include_once '/usr/share/php5-xhprof/xhprof_lib/utils/xhprof_runs.php';
			DebugBar::getInstance()->addCollector(new XhprofCollector());
		}
	}


	/**
	 * Register any error handling required
	 *
	 * @return void
	 */
	public function registerErrorHandling() {
		// noop. WebRequest does not use custom error handling
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
		register_shutdown_function([$this, 'shutdownSafely']);
	}


	/**
	 * Initialise connections to databases, etc.
	 *
	 * @return void
	 */
	public function initialiseExternalConnections() {
		$database = Database::getInstance();
		$database->connect(SQL_dsn, SQL_user, SQL_passwd, array('debug' => $this->debugEnabled));
		if($this->debugEnabled) {
			$debug_bar = DebugBar::getInstance();
			$debug_bar->addCollector($database->getCollector());

			$deprecated_database = DeprecatedDatabase::getInstance();
			$deprecated_database->installCollector();
			$debug_bar->addCollector($deprecated_database->getCollector());
		}

		// Redis connection configuration
		Redis::getInstance()->connect(Config::get('redis_url'));
	}


	/**
	 * Install any internal dependencies into the kernel (routing, etc.)
	 *
	 * @return void
	 */
	public function installDependencies() {
		// Set correct timezone
		date_default_timezone_set(Config::get('date_timezone'));

		// Set up the dependency injector

		// Do dependency injection stuff
		// How do I get this container elsewhere without using a global?
		$di = new Di\Container(new Di\Factory());

		// Configuration
		$di->set('config', $di->lazyNew('Schoolbox\\Config\\Loader'));

		// Web router
		$di->set(
			'router',
			$di->lazy(
				function () {
					$factory = new RouterFactory();
					return $factory->newInstance();
				}
			)
		);

		// Session
		$di->set(
			'session',
			$di->lazy(
				function () use ($di) {
					$factory = new Factory(
						$_COOKIE,
						$di->get('config'),
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
				function () {
					$db = Database::getInstance();
					$st = $db->query("SELECT term, singular_form, plural_form, capitalise_first FROM localisation");

					$l10n = [];

					while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
						$l10n[$r['term']] = [
							'singular'   => $r['singular_form'],
							'plural'     => $r['plural_form'],
							'capitalise' => (bool)$r['capitalise_first']
						];
					}

					return new RosettaStone($l10n);
				}
			)
		);

		// Preload of route parameters
		$di->set('preloader', $di->lazyNew('Alaress\\Web\\Router\\PathMatcher'));

		// Authorization
		$di->set('authorizer', $di->lazyNew('Alaress\\Authorizer\\Authorizer'));

		// Web request/response factory
		$di->params['Aura\\Web\\WebFactory'] = array('globals' => $GLOBALS);
		$di->set('webFactory', $di->lazyNew('Aura\\Web\\WebFactory'));

		// inject request and response into controller
		// (I may need to mangle requests in pre-processing though)
		$di->setters['Alaress\\Web\\Controller\\Base'] = array(
			'setRequest'    => $di->lazy(
				function () use ($di) {
					return $di->get('webFactory')->newRequest();
				}
			),
			'setResponse'   => $di->lazy(
				function () use ($di) {
					return $di->get('webFactory')->newResponse();
				}
			),
			'setSession'    => $di->lazyGet('session'),
			'setRouter'     => $di->lazyGet('router'),
			'setAuthorizer' => $di->lazyGet('authorizer'),
			'setDi'         => $di,
		);

		// inject web params (yep, even Sitepage!)
//		$di->setters['SitePage']['setDi'] = $di;
		$di->set('layoutEngine', $di->lazy(function() use ($di) {
			$sitepage = new \SitePage(false);
			$sitepage->setDi($di)->localise();
			return $sitepage;
		}));;
		$di->set('sitePage',     $di->lazy(function() use ($di) {
			$sitepage = new \SitePage();
			$sitepage->setDi($di)->localise();
			return $sitepage;
		}));

		// Web output class - currently uses Sitepage
		$di->setters['Alaress\\Web\\Output\\Html']['setTemplateEngine'] = $di->lazyGet('templateEngine');
		$di->setters['Alaress\\Web\\Output\\ThemedHtml']['setSitepage'] = $di->lazyGet('layoutEngine');
		$di->set('webOutput:html', $di->lazyNew('Alaress\\Web\\Output\\ThemedHtml'));
		$di->set('webOutput:nochrome', $di->lazyNew('Alaress\\Web\\Output\\Html'));
		$di->set('webOutput:json', $di->lazyNew('Alaress\\Web\\Output\\Json'));
		$di->set('webOutput:file', $di->lazyNew('Alaress\\Web\\Output\\File'));

		// Mapper notifications:
		// They rely upon the session being injected into them, so do that.
		$di->setters['Schoolbox\\Mapper']['setSessionData'] = $di->lazy(function() use($di) {
			/** @var \Alaress\Session\Session $session */
			$session = $di->get('session');
			$session->start();
			return isset($_SESSION) ? $_SESSION : array();
		});

		// Template engine and functions
		// Template loader: sets up link to templates without a namespace.
		$di->set(
			'templateEngine:loader',
			$di->lazy(
				function () use ($di) {
					$config      = $di->get('config');
					$chainLoader = new Twig_Loader_Chain();
					$baseLoader  = new Twig_Loader_Filesystem(array());
					// Paths: prefer templates/{site_location} over templates/core
					$basePath     = $config->get('siteRoot').'/templates/';
					$overridePath = $basePath.$config->get('site_location');
					$corePath     = $basePath.'core';
					if (is_dir($overridePath)) {
						$baseLoader->addPath($overridePath);
					}
					$baseLoader->addPath($corePath);
					// Core path: always uses templates/core, even if templates/{site_location} exists
					// Used as a base for default path templates to inherit from.
					$baseLoader->addPath($corePath, 'core');
					$chainLoader->addLoader($baseLoader);
					return $chainLoader;
				}
			)
		);

		$di->setters['Alaress\\Twig\\Method\\RouteLookup']['setRouter'] = $di->lazyGet('router');
		$di->set('templateEngine:routeLookup', $di->lazyNew('Alaress\\Twig\\Method\\RouteLookup'));

		$di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setRouter']     = $di->lazyGet('router');
		$di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setAuthorizer'] = $di->lazyGet('authorizer');
		$di->setters['Alaress\\Twig\\Method\\IsRouteAuthorized']['setSession']    = $di->lazyGet('session');
		$di->set('templateEngine:isRouteAuthorized', $di->lazyNew('Alaress\\Twig\\Method\\IsRouteAuthorized'));

		$di->set(
			'templateEngine',
			$di->lazy(
				function () use ($di) {
					$config = $di->get('config');
					$twig   = new \Twig_Environment(
						$di->get('templateEngine:loader'),
						array(
							'cache' => $config->get('stream_dir') . '/templates/',
							'debug' => $config->get('debug_templates'),
						)
					);

                    if($this->debugEnabled) {
                        $twig = new TraceableTwigEnvironment($twig);
                        DebugBar::getInstance()->addCollector(new TwigCollector($twig));
                    }

					$twig->addExtension(new Twig_Extension_Debug());
					$twig->addExtension(new Twig_Extensions_Extension_Text());

					// HTML Purifier escape strategy
					// Please use the filter below instead.
					$twig->getExtension('core')
						 ->setEscaper('purify', function ($twig, $string) {
							 return Scrub::html($string);
						});

					// HTML Purifier filter - known to output safe HTML
					$twig->addFilter(
						new Twig_SimpleFilter(
							'purify',
							array('Scrub', 'html'),
							array('is_safe' => array('html'))
						)
					);

					// HTML2Text filter
					$twig->addFilter(
						new Twig_SimpleFilter(
							'html2text',
							array('Scrub', 'html2text')
						)
					);

					// Cut sentence filter
					$twig->addFilter(
						new Twig_SimpleFilter(
							'cutSentence',
							array('TextFormatter', 'cutSentence')
						)
					);

					// Bytes to string filter
					$twig->addFilter(
						new Twig_SimpleFilter(
							'bytes',
							array('Alaress_Byte_Format', 'bytesToString')
						)
					);

					// Global access to config object and localisation
					$twig->addGlobal('config', $config);

					$l10n = $di->get('l10n');

//					$l10n['date'] = new Alaress_Date_Locale_Config();

					$twig->addGlobal('locale', $l10n);

					// Add the route lookup and authorization methods
					$twig->addFunction(
						new Twig_SimpleFunction(
							'routeLookup',
							array($di->get('templateEngine:routeLookup'), 'execute')
						)
					);
					$twig->addFunction(
						new Twig_SimpleFunction(
							'isRouteAuthorized',
							array($di->get('templateEngine:isRouteAuthorized'), 'execute')
						)
					);

					return $twig;
				}
			)
		);

		// Paginator internal object
		$di->setters['Alaress\\Paginator\\UrlPaginator']['setPaginator'] = $di->lazyNew('Alaress\\Paginator\\Paginator');

		// load dependencies into the kernel itself
		$this->kernel->setDependencyInjector($di);
		$this->kernel->setDebugMode($this->debugEnabled);


		// Initialise module configuration
		require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
        $customModuleLocation = sprintf(
            '/conf/modules/Custom/%s/',
            ucfirst($this->kernel->getDependencyInjector()->get('config')->get('site_location'))
        );
        $modulePaths = array_merge(
            glob(SITE_root . '/conf/modules/*.php'),
            glob(SITE_root . $customModuleLocation . '*.php')
        );

		foreach ($modulePaths as $routePath) {
			require_once $routePath;

            // Get a route name out of this path
//            $path = dirname($routePath);
            $path = preg_replace('/^'.preg_quote(SITE_root.'/conf/modules/', '/').'/', '', $routePath);
            $path = preg_replace('/\\.php$/', '', $path);
            $path = preg_replace('/\\//', '\\', $path);

//			$start = strrpos($routePath, '/') + 1;
//			$length = strrpos($routePath, '.php') - $start;
			$class = 'Config\\Modules\\' . $path;

			if(class_exists($class, false)) {
				/** @var Config\Modules\ModuleConfigInterface $obj */
				$obj = new $class();
				$obj->configure($this->kernel);
			}

		}

	}

    /**
     * Shutdown handler for either exceptions or fatal errors
     *
     * @param \Exception $e
     */
    public function shutdownSafely(\Exception $e = null)
    {
        $outputStackTrace = Config::get('debug_show_stack_trace');
        if ($e instanceof \Exception) {
            $error = array(
                'file'    => $e->getFile(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            );
            if ($outputStackTrace) {
                $error['message'] = "<pre>$e</pre>";
            }
        } else {
            $error = error_get_last();
        }

        // it's almost a guarantee that some kind of minor error will have occurred at some point
        if ($error !== null) {

            // use the document_root to get a Schoolbox-relative file in which the error occurred
            $cause = substr($error['file'], strlen($_SERVER['DOCUMENT_ROOT']) - 4);

            // if it was a fatal error or exception that triggered our sudden but inevitable demise...
            if (
                (intval($error['type']) & (E_ERROR | E_CORE_ERROR | E_USER_ERROR | E_COMPILE_ERROR))
                || $e instanceof \Exception
            ) {
                // RedisException extends RuntimeException: occurs when Redis
                // session management isn't working for whatever reason.
                $code    = 400;
                $message = 'Schoolbox has encountered a fatal error.';
                $info    = "If the problem persists, please contact your System Administrator including the following information:";
                if ($e instanceof \RuntimeException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently down due to an internal service being inactive.';
                } elseif ($e instanceof \PDOException) {
                    $code    = 500;
                    $message = 'Schoolbox is currently experiencing internal problems with its database. This issue has been logged.';
                } else {
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

                // Report to New Relic if not a RuntimeException
                if (extension_loaded('newrelic')) {
                    if ($e instanceof \Exception && !($e instanceof \RuntimeException)) {
                        newrelic_notice_error(null, $e);
                    }
                }

                // explain that something went cataclysmically wrong.
                $output =
                    "<p><b>$message $info</b></p>" .
                    '<ul>' .
                    '<li>Datetime: ' . date('Y-m-d H:i:s') . '</li>' .
                    '<li>URL: http' . (getenv("SERVER_PORT") == 443 ? 's://' : '://') .
                        $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .
                    '</li>';
                if ($outputStackTrace) {
                    $output .= "<li>Error: {$error['message']} in {$error['file']} on line {$error['line']}.</li>";
                } else {
                    $output .= "<li>Error: {$error['message']}. (Further details have been logged.)</li>";
                }
                $output .= '</ul>';

                // Use Sitepage to output an error message in the school's design.
                $page = new \SitePage(false);
                $page->error(null, $output, $code);
            }
        }
    }
}