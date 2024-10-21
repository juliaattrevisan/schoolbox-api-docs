<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\BasicRequest;

use Alaress\Codec\Json\Base64;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Image\ProcessorFactory;
use Alaress\Redis\Cache\ItemPool;
use Alaress\Redis\Cache\KeyGenerator;
use Alaress\Sentry\Tracing\Tracer;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use enshrined\svgSanitize\Sanitizer as BaseSvgSanitizer;
use GuzzleHttp;
use League\Flysystem\Filesystem;
use Proxy\Server as ProxyServer;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\API\Token\JwtParser;
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
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Instance;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Notif\MQ\ConnectorFactory;
use Schoolbox\Notif\Notifactory;
use Schoolbox\IConfigLoader;
use Schoolbox\Oidc\CacheHelper;
use Schoolbox\Oidc\Server\RelyingParty\Mapper as RelyingPartyMapper;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\HashGenerator;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Receiver;
use Schoolbox\Resource\Storage\Sanitizer;
use Schoolbox\TaskDaemon\ProducerFactory;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\User\Token\Mapper as TokenMapper;
use Schoolbox\UserMapper;
use Schoolbox\Version\Version;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionSource;
use SitePage;
use Twig\Environment;
use Alaress\Twig\Method as TwigMethod;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
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
    public static function installDependencies(Di\Container $di): Di\Container
    {
        // Config loader
        $config = new ConfigLoader();
        $di->set('config', $config);

        // Redis cache (memory-only, not persistent)
        Redis::getCacheInstance()->connect($config->get('redis_ephemeral_url'), null, INSTANCE_ID);
        $di->set('redis.cache', Redis::getCacheClientInstance());

        // Redis storage (persisted to disk)
        Redis::getStorageInstance()->connect($config->get(ConfigMapper::NAME_REDIS_URL), null, INSTANCE_ID);
        $di->set('redis.storage', Redis::getStorageClientInstance());

        // Redis queue publish (not persistent)
        // (The subscription to this queue doesn't exist in the standard kernel,
        //  only in the daemon kernel: this is because it requires the event
        //  loop)
        Redis::aliasInstance(Redis::INSTANCE_CACHE, Redis::INSTANCE_SIGNAL_QUEUE);
        $di->set('redis.signalQueue.pub', Redis::getSignalQueueClientInstance());

        // User token generator
        $di->set(
            'user.tokenBuilder',
            $di->lazyNew(
                Jwt::class,
                ['configLoader' => $config]
            )
        );

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
                [
                    'instance'     => $di->lazyGet('instance'),
                    'tokenBuilder' => $di->lazyGet('user.tokenBuilder'),
                ],
            )
        );

        $di->set(
            'amqp',
            $di->lazy(
                fn () => (new ConnectorFactory($config))->build([], []),
            ),
        );

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
                $conf->get('site_environment') ?? Version::SITE_ENVIRONMENT_CLIENT_HOSTED,
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

        // Schoolbox internal read-only database connection
        $di->set(
            'databaseInternalRead',
            function () use ($di) {
                /** @var SchoolboxDatabaseFactory $factory */
                $factory = $di->get('databaseFactory');
                return $factory->getInternalReadDatabase();
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
        static::installSessionService($di);

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

        $di->set(
            'proxyServer',
            $di->lazyNew(
                ProxyServer::class,
                ['conf' => $di->lazyGet('config')]
            )
        );

        // TODO this should be used wherever a HTTP client is used
        $di->set(
            'httpClient',
            $di->lazy(
                static function () use ($di) {
                    /** @var ProxyServer $proxyServer */
                    $proxyServer = $di->get('proxyServer');
                    return new GuzzleHttp\Client(['curl' => $proxyServer->buildCurlOptions()]);
                }
            ),
        );

        // add sentry to the DI
        $di->set(
            'sentry',
            $di->lazy(
                static function () use ($di, $config) {
                    $sentryToken   = defined('SENTRY_TOKEN') ? SENTRY_TOKEN : null;
                    $sentryProject = defined('SENTRY_PROJECT') ? SENTRY_PROJECT : null;
                    /** @var Instance $sb */
                    $sb = $di->get('instance');
                    return (new \Alaress\Sentry\Factory())->build(
                        $sb,
                        Instance::APPLICATION_NAME,
                        $sentryToken,
                        $sentryProject,
                        $config,
                    );
                }
            )
        );

        // JWT parser
        $di->set(
            'user.jwtParser',
            $di->lazy(
                function () use ($di, $config, $mapperFactory) {
                    /** @var GuzzleHttp\Client $httpClient */
                    $httpClient             = $di->get('httpClient');
                    /** @var CacheInterface $oidcCache */
                    $oidcCache              = $di->get('oidc.cache');
                    $userMapper             = $mapperFactory->get(UserMapper::class);
                    $tokenMapper            = $mapperFactory->get(TokenMapper::class);
                    $relyingPartyMapper     = $mapperFactory->get(RelyingPartyMapper::class);
                    return new JwtParser\Composite(
                        new JwtParser\OtherInstanceIssued($userMapper, $config),
                        new JwtParser\SelfIssued($userMapper, $config, $tokenMapper),
                        new JwtParser\OauthProviderIssued($userMapper, $config, $httpClient, $oidcCache),
                        new JwtParser\InstanceRegistryIssued($userMapper, $config),
                        new JwtParser\OauthClientHeld($userMapper, $config, $relyingPartyMapper),
                    );
                }
            )
        );

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

                    // HTML Purifier filter - known to output safe HTML
                    $scrubAdapter = new ScrubAdapter();
                    $twig->addFilter(
                        new TwigFilter(
                            'purify',
                            [$scrubAdapter, 'purify'],
                            ['is_safe' => ['html']]
                        )
                    );

                    // HTML2Text filter
                    $twig->addFilter(
                        new TwigFilter(
                            'html2text',
                            ['Scrub', 'html2text']
                        )
                    );

                    // Cut sentence filter
                    $twig->addFilter(
                        new TwigFilter(
                            'cutSentence',
                            ['TextFormatter', 'cutSentence']
                        )
                    );

                    // Bytes to string filter
                    $twig->addFilter(
                        new TwigFilter(
                            'bytes',
                            ['Alaress_Byte_Format', 'bytesToString']
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
                            ],
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

        // OIDC things which aren't related to LTI
        // This is the cache for the response from the OIDC discovery and JWKS
        // requests.
        $di->set(
            'oidc.cache',
            $di->lazy(
                function () use ($di) {
                    return new SimpleCacheBridge(
                        new ItemPool(
                            $di->get('redis.cache'),
                            new Base64(),
                            new KeyGenerator(CacheHelper::BASE_CLIENT_KEY, INSTANCE_ID),
                        )
                    );
                }
            )
        );

        // ClickView homepage component cache for storing URL responses from the ClickView API
        $di->set(
            'component.homepage.clickview.cache',
            $di->lazy(
                function () use ($di) {
                    return new SimpleCacheBridge(
                        new ItemPool(
                            $di->get('redis.cache'),
                            new Base64(),
                            new KeyGenerator('component.homepage.clickview', INSTANCE_ID),
                        )
                    );
                }
            )
        );

        $di->set(
            'paperly.cache',
            $di->lazy(
                function () use ($di) {
                    return new SimpleCacheBridge(
                        new ItemPool(
                            $di->get('redis.cache'),
                            new Base64(),
                            new KeyGenerator('paperly', INSTANCE_ID),
                        )
                    );
                }
            )
        );

        // Filesystems
        $di->set(
            'filesystem.storage',
            $di->lazy(
                function () use ($config) {
                    return (new FilesystemFactory())->buildStorage($config);
                }
            )
        );
        $di->set(
            'filesystem.storageProxy',
            $di->lazy(
                function () use ($di, $config) {
                    /** @var Filesystem $storage */
                    $storage = $di->get('filesystem.storage');
                    return new FilesystemProxy(
                        $storage,
                        new HashGenerator(),
                        $config->get(ConfigMapper::NAME_OTFS_VERIFY_PREVIOUS_FILE),
                        FilesystemFactory::buildDefaultVisibility($config->get(ConfigMapper::NAME_OTFS_ADAPTER)),
                    );
                }
            )
        );
        $di->set(
            'filesystem.receiver',
            $di->lazyNew(
                Receiver::class,
                [
                    'storageMapper'          => $mapperFactory->getLazy(StorageMapper::class),
                    'mimetypeMapper'         => $mapperFactory->getLazy(MimetypeMapper::class),
                    'storageFilesystemProxy' => $di->lazyGet('filesystem.storageProxy'),
                    'sanitizer'              => $di->lazyGet('storage.sanitizer'),
                    'tempFilesystem'         => $di->lazyGet('filesystem.temp'),
                    // CLI scripts have no current user, thus this is null
                    'currentUser'            => (PHP_SAPI === 'cli' ? null : $di->lazyGet('user')),
                ],
            )
        );
        $di->set(
            'filesystem.temp',
            $di->lazy(
                function () {
                    return (new FilesystemFactory())->buildTemp();
                }
            )
        );
        $di->set(
            'filesystem.imageProcessorFactory',
            $di->lazy(
                function () use ($di) {
                    /** @var Filesystem $storage */
                    $storage = $di->get('filesystem.storage');
                    return new ProcessorFactory($storage, new HashGenerator());
                }
            ),
        );

        return $di;
    }

    /**
     * Helper function for basic requests only to set up a Sentry transaction:
     * needs to be called manually with the filename of the script which created
     * the basic request.
     *
     * @param string $path The full path of the script which was invoked:
     *                     this path (minus the site root) will be used as the
     *                     name of the transaction.
     * @return void
     */
    public function startSentryTransaction(string $path): void
    {
        $trace = Tracer::getDefaultTrace();
        $trace->setTransactionContextForBasicRequest($path);
    }
}
