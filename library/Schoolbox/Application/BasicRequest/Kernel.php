<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\BasicRequest;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Image\ProcessorFactory;
use enshrined\svgSanitize\Sanitizer as BaseSvgSanitizer;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;
use Schoolbox\Application\WebKernelAbstract;
use Aura\Di;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Database\Factory as SchoolboxDatabaseFactory;
use Alaress\Database\Factory as AlaressDatabaseFactory;
use Alaress\Redis;
use Schoolbox\Instance;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notifactory;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\HashGenerator;
use Schoolbox\Resource\Storage\Sanitizer;
use Schoolbox\TaskDaemon\ProducerFactory;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use SitePage;
use Twig\Environment;
use Alaress\Twig\Method as TwigMethod;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Schoolbox\Admin\Navigation\Renderer as AdminNavigationRenderer;
use Alaress_Date_Locale_Config;
use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\Redirect;

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
     * @param Di\Container $di
     * @return Di\Container
     * @throws Di\Exception
     * @throws Di\Exception\ContainerLocked
     * @throws Di\Exception\ServiceNotFound
     * @throws Di\Exception\ServiceNotObject
     * @throws \RuntimeException
     */
    protected function installDependencies(Di\Container $di): Di\Container
    {
        // Config loader
        $config = new ConfigLoader();
        $di->set('config', $config);

        // Redis cache (memory-only, not persistent)
        Redis::getCacheInstance()->connect($config->get('redis_ephemeral_url'), null, INSTANCE_ID);
        $di->set('redis.cache', Redis::getCacheClientInstance());

        // Redis storage (persisted to disk)
        Redis::getStorageInstance()->connect($config->get('redis_url'), null, INSTANCE_ID);
        $di->set('redis.storage', Redis::getStorageClientInstance());

        // Redis queue publish (not persistent)
        // (The subscription to this queue doesn't exist in the standard kernel,
        //  only in the daemon kernel: this is because it requires the event
        //  loop)
        Redis::aliasInstance(Redis::INSTANCE_CACHE, Redis::INSTANCE_SIGNAL_QUEUE);
        $di->set('redis.signalQueue.pub', Redis::getSignalQueueClientInstance());

        // Default Redis connection:
        // uses cache, and raises a warning telling you to choose a connector
        $di->set('redis', $di->lazy(function () use ($di) {
            trigger_error(
                'The default "redis" connection is deprecated; falling back to using "redis.storage". (Use "redis.cache" for memory-only or "redis.storage" for persistent storage)',
                E_USER_WARNING,
            );
            return $di->get('redis.storage');
        }));

        // Task daemon message producer
        $di->set(
            'taskd.messageProducer',
            $di->lazy(
                function () use ($config) {
                    return (new ProducerFactory())->build(
                        $config->get(ConfigMapper::NAME_TASKD_REDIS_URL),
                        $config->get(ConfigMapper::NAME_TASKD_REDIS_QUEUE)
                    );
                }
            )
        );
        $di->set(
            'taskd.messageFactory',
            $di->lazyNew(
                MessageFactory::class,
                ['instance' => $di->lazyGet('instance')],
            )
        );

        $di->set('amqp', $di->lazy(function () {
            return Connector::getInstance();
        }));

        $di->set('instance', $di->lazy(function () use ($di) {
            /** @var IConfigLoader $conf */
            $conf = $di->get('config');
            return new Instance(
                INSTANCE_ID,
                $conf->get('site_hostname'),
                $conf->get('site_domain'),
                $conf->get('site_version'),
                $conf->get('site_type'),
                $conf->get('site_school_code'),
                $conf->get('site_environment'),
            );
        }));

        // install mapper DI
        // (yes I know this is icky, but it will have to do until mapper construction is set up with DI properly)
        Mapper::di($di);

        // Set correct timezone
        date_default_timezone_set($config->get('date_timezone'));

        // Notification factory
        $di->set(
            'notif',
            $di->lazy(
                function () {
                    // replace this with $di->lazyGetCall() once we upgrade Aura.Di
                    return Notifactory::create();
                }
            )
        );

        // Database connection factory
        $di->set(
            'databaseFactory',
            function () use ($di) {
                return new SchoolboxDatabaseFactory(
                    new AlaressDatabaseFactory()
                );
            }
        );

        // Schoolbox internal database connection
        $di->set(
            'databaseInternal',
            function () use ($di) {
                /** @var SchoolboxDatabaseFactory $factory */
                $factory = $di->get('databaseFactory');
                return $factory->getInternalDatabase();
            }
        );

        // External SIS database connection(s)
        $di->set(
            'databaseExternal',
            $di->lazy(
                function () use ($di) {
                    /** @var SchoolboxDatabaseFactory $factory */
                    $factory = $di->get('databaseFactory');

                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    return $factory->getExternalDatabase($config);
                }
            )
        );

        // Session
        $this->installSessionService($di);

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

        // Localisation
        $di->set(
            'l10n',
            $di->lazy(
                function () use ($di) {
                    $stoneFactory = new RosettaStoneFactory();
                    return $stoneFactory->build($di->get('mapperFactory')->get(L10nMapper::class));
                }
            )
        );

        // CDN prefix generator
        $di->set(
            'cdnPrefixGenerator',
            $di->lazyNew(PrefixGenerator::class, ['config' => $di->lazyGet('config')])
        );

        // Enable mappers to be injectable, but instantiated only when required
        $mapperFactory = new MapperFactory($di);
        $di->set('mapperFactory', $mapperFactory);

        // install sentry
        $sentryToken = \defined('SENTRY_TOKEN') ? SENTRY_TOKEN : '';
        $sentryProject = \defined('SENTRY_PROJECT') ? SENTRY_PROJECT : '';
        /** @phpstan-ignore-next-line */
        if (\strlen($sentryToken) && \strlen($sentryProject)) {
            // add sentry to the DI
            $di->set(
                'sentry',
                $di->lazy(
                    static function () use ($di, $sentryToken, $sentryProject) {
                        try {
                            /** @var Instance $sb */
                            $sb = $di->get('instance');
                            return (new \Alaress\Sentry\Factory())->build(
                                $sb,
                                Instance::APPLICATION_NAME,
                                $sentryToken,
                                $sentryProject
                            );
                        } catch (Di\Exception\ServiceNotFound $e) {
                            // do nothing; sentry is not installed
                        }
                    }
                )
            );
        }

        $di->set(
            'templateEngine',
            $di->lazy(
                function () use ($di) {
                    /** @var IConfigLoader $config */
                    $config = $di->get('config');

                    $options = [
                        'debug'            => $config->get('debug_templates'),
                        'strict_variables' => $config->get('strict_templates'),
                    ];

                    // do not cache when in debug mode
                    if (!$config->get('debug_mode')) {
                        $options['cache'] = $config->get('twig_cache_dir');
                    }

                    /** @var FilesystemLoader $loader */
                    $loader = $di->get('templateEngine:loader');
                    $twig = new Environment($loader, $options);

                    $twig->addExtension(new DebugExtension());
                    $twig->addExtension(new AdminNavigationRenderer());

                    // HTML Purifier filter - known to output safe HTML

                    $twig->addFilter(
                        new TwigFilter(
                            'purify',
                            array('Scrub', 'html'),
                            array('is_safe' => array('html'))
                        )
                    );

                    // HTML2Text filter
                    $twig->addFilter(
                        new TwigFilter(
                            'html2text',
                            array('Scrub', 'html2text')
                        )
                    );

                    // Cut sentence filter
                    $twig->addFilter(
                        new TwigFilter(
                            'cutSentence',
                            array('TextFormatter', 'cutSentence')
                        )
                    );

                    // Bytes to string filter
                    $twig->addFilter(
                        new TwigFilter(
                            'bytes',
                            array('Alaress_Byte_Format', 'bytesToString')
                        )
                    );

                    $twig->addFilter(
                        new TwigFilter(
                            'column',
                            [Collection::class, 'arrayColumn']
                        )
                    );

                    // Global access to config object and localisation
                    $twig->addGlobal('config', $config);
                    $twig->addGlobal('locale', $di->get('l10n'));
                    $dateFormats = new Alaress_Date_Locale_Config();
                    $dateFormats->setConfig($config);
                    $twig->addGlobal('dateFormats', $dateFormats);
                    $twig->addGlobal('NOW', Alaress_Date_Format::now());

                    // This value is liable to be overridden by the user within
                    // the controller
                    $twig->addGlobal('USER', $di->get('user'));

                    // CDN_PREFIX
                    /** @var PrefixGenerator $cdnPrefixGenerator */
                    $cdnPrefixGenerator = $di->get('cdnPrefixGenerator');
                    $twig->addGlobal('CDN_PREFIX', $cdnPrefixGenerator->generate());

                    // Global access to returnUrl vars
                    $encodedUrl = Redirect::encode(RequestUriParser::parseUrlPath($_SERVER));
                    $twig->addGlobal(
                        'RETURN_URL',
                        [
                            'name'     => Redirect::RETURN_URL,
                            'current'  => $encodedUrl,
                            'previous' => [
                                'encoded' => $_REQUEST[Redirect::RETURN_URL] ?? null,
                                'decoded' => isset($_REQUEST[Redirect::RETURN_URL])
                                    ? Redirect::decode($_REQUEST[Redirect::RETURN_URL])
                                    : null,
                            ]
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
                    $baseLoader  = new FilesystemLoader(array());
                    // Paths: prefer templates/{site_school_code} over templates/core
                    $basePath     = SITE_root . '/templates/';
                    $overridePath = $basePath . $config->get('site_school_code');
                    $corePath     = $basePath . 'core';
                    if (is_dir($overridePath)) {
                        $baseLoader->addPath($overridePath);
                    }
                    $baseLoader->addPath($corePath);
                    // Core path: always uses templates/core, even if templates/{site_school_code} exists
                    // Used as a base for default path templates to inherit from.
                    $baseLoader->addPath($corePath, 'core');
                    return $baseLoader;
                }
            )
        );

        $di->set('templateEngine:requireModule', $di->lazyNew(TwigMethod\RequireModule::class, ['di' => $di]));

        // Unfortunately basic request needs a SitePage for now
        $di->set(
            'sitePage',
            $di->lazyNew(SitePage::class, ['di' => $di])
        );

        // Storage upload/download sanitizer
        $di->set(
            'storage.sanitizer',
            $di->lazy(function () use ($di) {
                /** @var IConfigLoader $config */
                $config = $di->get('config');
                $baseSvgSanitizer = new BaseSvgSanitizer();
                $baseSvgSanitizer->minify(true);
                return new Sanitizer\Composite(
                    new Sanitizer\Svg($baseSvgSanitizer),
                    new Sanitizer\Html(new ScrubAdapter()),
                    new Sanitizer\Exif($config)
                );
            })
        );

        // Filesystems
        $di->set(
            'filesystem.mountManager',
            $di->lazy(
                function () use ($config) {
                    return (new FilesystemFactory())->build($config->get('storage_dir'));
                }
            )
        );
        $di->set(
            'filesystem.storage',
            $di->lazy(
                function () use ($di) {
                    /** @var MountManager $mountManager */
                    $mountManager = $di->get('filesystem.mountManager');
                    return $mountManager->getFilesystem(FilesystemFactory::FILE_SYSTEM_STORAGE);
                }
            )
        );
        $di->set(
            'filesystem.storageProxy',
            $di->lazy(
                function () use ($di) {
                    /** @var FilesystemInterface $storage */
                    $storage = $di->get('filesystem.storage');
                    return new FilesystemProxy($storage, new HashGenerator());
                }
            )
        );
        $di->set(
            'filesystem.temp',
            $di->lazy(
                function () use ($di) {
                    /** @var MountManager $mountManager */
                    $mountManager = $di->get('filesystem.mountManager');
                    return $mountManager->getFilesystem(FilesystemFactory::FILE_SYSTEM_TEMP);
                }
            )
        );
        $di->set(
            'filesystem.imageProcessorFactory',
            $di->lazy(
                function () use ($di) {
                    /** @var FilesystemInterface $storage */
                    $storage = $di->get('filesystem.storage');
                    return new ProcessorFactory($storage, new HashGenerator());
                }
            ),
        );


        return $di;
    }
}
