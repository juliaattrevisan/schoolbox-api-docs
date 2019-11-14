<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\BasicRequest;

use Schoolbox\Application\WebKernelAbstract;
use Aura\Di;
use Psr\Log\NullLogger;
use Schoolbox\Collection;
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
use Schoolbox\IConfigLoader;
use SitePage;
use Twig_Environment;
use Alaress\Twig\Method as TwigMethod;
use Twig_Extension_Debug;
use Twig_Extensions_Extension_Text;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use Schoolbox\Admin\Navigation\Renderer as AdminNavigationRenderer;
use Alaress_Date_Locale_Config;
use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\Redirect;
use Predis;

/**
 * This kernel is a stripped down version of the full web request kernel.  It is designed for use when there is
 * no user interface to display.  For example streaming files, images and videos.
 */
class Kernel extends WebKernelAbstract
{

    /**
     * @inheritdoc
     */
    protected function initialiseDebugging(): void
    {
    }


    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * @return void
     * @throws Di\Exception
     * @throws Di\Exception\ContainerLocked
     * @throws Di\Exception\ServiceNotFound
     * @throws Di\Exception\ServiceNotObject
     * @throws \RuntimeException
     */
    protected function installDependencies(): void
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
                    /** @var Predis\ClientInterface $redis */
                    $redis = $di->get('redis');

                    $factory = new Factory(
                        $_COOKIE,
                        $config,
                        $redis,
                        $_SERVER['REQUEST_TIME']
                    );
                    return $factory->build();
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
        /** @var \Alaress\Session\Session $session */
        $session = $di->get('session');
        if (!$session->isStarted()) {
            $session->start();
        }
        // install sentry
        $sentryToken = \defined('SENTRY_TOKEN') ? SENTRY_TOKEN : '';
        $sentryProject = \defined('SENTRY_PROJECT') ? SENTRY_PROJECT : '';
        $sentryClient = null;
        if (\strlen($sentryToken) && \strlen($sentryProject)) {
            try {
                /** @var \Schoolbox\Instance $sb */
                $sb = $di->get('instance');
                // fall back to site_location if site_school_code not set
                $options = [
                    'environment' => $sb->getType(),
                    'tags' => [
                        'php_version' => PHP_VERSION,
                        'site_version' => $sb->getVersion(),
                        'site_school_code' => $sb->getSchoolCode() ?: $sb->getLocation()
                    ]
                ];
                $sentryClient = new \Raven_Client("https://{$sentryToken}@sentry.io/{$sentryProject}", $options);
                $sentryClient->install();
            } catch (\Raven_Exception $e) {
                // do nothing; sentry is not installed
            } catch (Di\Exception\ServiceNotFound $e) {
                // do nothing; sentry is not installed
            }

            // add sentry to the DI
            $di->set('sentry', $sentryClient);
        }

        $di->set('layoutEngine', $di->lazy(function() use ($di) {
            $sitepage = new SitePage(false);
            $sitepage->setDi($di);
            return $sitepage;
        }));

        $di->set(
            'templateEngine',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    $options = [
                        'debug' => $config->get('debug_templates'),
                    ];

                    // do not cache when in debug mode
                    if (!$config->get('debug_mode')) {
                        $options['cache'] = $config->get('stream_dir') . '/templates/';
                    }

                    /** @var Twig_Loader_Filesystem $loader */
                    $loader = $di->get('templateEngine:loader');
                    $twig = new Twig_Environment($loader, $options);

                    $twig->addExtension(new Twig_Extension_Debug());
                    $twig->addExtension(new Twig_Extensions_Extension_Text());
                    $twig->addExtension(new AdminNavigationRenderer());

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

                    $twig->addFilter(
                        new Twig_SimpleFilter(
                            'column',
                            [ Collection::class, 'array_column' ]
                        )
                    );

                    // Global access to config object and localisation
                    $twig->addGlobal('config', $config);
                    $twig->addGlobal('locale',  $di->get('l10n'));
                    $dateFormats = new Alaress_Date_Locale_Config();
                    $dateFormats->setConfig($config);
                    $twig->addGlobal('dateFormats', $dateFormats);
                    $twig->addGlobal('NOW', Alaress_Date_Format::now());
                    $twig->addGlobal('USER', $_SESSION['user']);

                    // Global access to returnUrl vars
                    $encodedUrl = Redirect::encode($_GET["page"]);
                    $twig->addGlobal(
                        'RETURN_URL',
                        [
                            'name'     => Redirect::RETURN_URL,
                            'current'  => $encodedUrl,
                            'previous' => $_REQUEST[Redirect::RETURN_URL] ?? $encodedUrl,
                        ]
                    );

                    return $twig;
                }
            )
        );

        // Template engine and functions
        // Template loader: sets up link to templates without a namespace.
        $di->set(
            'templateEngine:loader',
            $di->lazy(
                function () use ($di) {
                    $config      = $di->get('config');
                    $baseLoader  = new Twig_Loader_Filesystem(array());
                    // Paths: prefer templates/{site_location} over templates/core
                    $basePath     = SITE_root.'/templates/';
                    $overridePath = $basePath.$config->get('site_location');
                    $corePath     = $basePath.'core';
                    if (is_dir($overridePath)) {
                        $baseLoader->addPath($overridePath);
                    }
                    $baseLoader->addPath($corePath);
                    // Core path: always uses templates/core, even if templates/{site_location} exists
                    // Used as a base for default path templates to inherit from.
                    $baseLoader->addPath($corePath, 'core');
                    return $baseLoader;
                }
            )
        );

        $di->params[TwigMethod\RequireModule::class]['di'] = $di;
        $di->set('templateEngine:requireModule', $di->lazyNew(TwigMethod\RequireModule::class));


        // load dependencies into the kernel itself
        $this->setDependencyInjector($di);
    }
}
