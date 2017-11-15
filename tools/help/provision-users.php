#!/usr/bin/php
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Config\Modules\Custom\Help\API;
use Custom\Help\CourseParticipation\Handler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;


$fields = [ 'schoolCode:', 'host:' ];
$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Help User Provision', [
    'verbose'    => $opts['v'] ?? null,
    'errors'     => E_ALL,
    'fields'     => array_keys($opts),
    'schoolCode' => $opts['schoolCode'] ?? null,
    'host'       => $opts['host'] ?? null,
]);

// ensure the help routes are registered, so we can do route lookups
// will explode if you're actually on Help (thank goodness)
require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
require_once SITE_root . '/conf/ModuleLoader.php';
require_once SITE_root . '/conf/modules/Custom/Help/API.php';
(new API())->configure($kernel);


// PROVISIONING APP
$kernel->run(new class extends App {

    const DEFAULT_SERVER   = 'https://help.schoolbox.com.au/';
    const ROUTE_USERS_GET  = 'getUsersBySchool';
    const ROUTE_USERS_POST = 'postUserRegistrations';

    /** @var Client */
    private $http;

    /** @var Router */
    private $router;

    /** @var string */
    private $schoolCode;

    /** @var Handler */
    private $handler;


    /**
     * We don't have access to the DI during construction, so perform post-construction setup
     *
     * @param Di\Container $di
     * @param Logger       $log
     * @param array        $opts
     * @throws Di\Exception\ServiceNotFound
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function setup(Di\Container $di, Logger $log, array $opts)
    {
        $this->http = new Client([
            'base_uri' => $opts['host'] ?? self::DEFAULT_SERVER,
        ]);

        /** @var IConfigLoader $config */
        $config = $di->get('config');

        /** @var MapperFactory $mapFactory */
        $mapFactory = $di->get('mapperFactory');

        $this->schoolCode = trim($opts['schoolCode'] ?? $config->get('site_school_code'));

        if (strlen($this->schoolCode) === 0) {
            // die in a fiery pit of doom
            throw new InvalidArgumentException('config.site_school_code is not configured; either configure in database or specify on commandline');
        }

        $this->router = $di->get('router');

        /** @noinspection PhpParamsInspection */
        $this->handler = new Handler(
            $mapFactory->get(UserAccessMapper::class),
            $mapFactory->get(Schoolbox\Permission\SiteAccess\Mapper::class),
            $mapFactory->get(Schoolbox\Resource\Folder\Mapper::class),
            new Schoolbox\User\Password\MailService(
                $config,
                $mapFactory->get(Schoolbox\User\Password\Reset\Mapper::class),
                $mapFactory->get(Schoolbox\UserMapper::class),
                new TwigEmailFactory(new CoreContext($config), $log)
            ),
            $config->get('portrait_dir')
        );
    }


    /**
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     * @throws Di\Exception\ServiceNotFound
     * @throws RuntimeException
     * @return void
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts)
    {
        $this->setup($di, $log, $opts);

        $host = $this->http->getConfig('base_uri');

        try {
            $log->info("Fetching '{$this->schoolCode}' user list from {$host}");

            // TODO once fuzziness is no longer required, remove the fuzzy argument
            /** @var array $provisionedUsers */
            $provisionedUsers = json_decode($this->getFromHelp(self::ROUTE_USERS_GET, [ 'fuzzy' => 1 ]), true);

            // obtain list of user IDs
            $userIds = array_keys($provisionedUsers);

            $users = UserMapper::getInstance()->findByColumn('id', $userIds);

            $userData = [];

            // extract the data in the format we need (unfortunately, no, this can't be done via DB-only)
            // the models contain a bit of logic we require -_-
            foreach ($users as $user) {
                $log->debug("Processing {$user->formatName()}");

                foreach (Handler::REQUIRED_USER_FIELDS as $field) {
                    $userData[$user->getId()][$field] = trim(UserMapper::getInstance()->getModelProperty($user, $field));
                }
            }

            // strip indexing
            $userData = array_values($userData);

            $log->notice("Pushing {$users->count()} users to {$host}");

            try {
                // send the data off
                $this->postToHelp(self::ROUTE_USERS_POST, [ 'user' => $userData ], [ 'byUsername' => '1' ]);
            } catch (ClientException $e) {
                $r = $e->getResponse();

                if ($r) {
                    $log->warning("Received client error: {$r->getStatusCode()} {$r->getReasonPhrase()}");

                    /** @var array $failedUsers */
                    $failedUsers = json_decode($r->getBody()->getContents(), true);

                    if (is_array($failedUsers)) {
                        foreach ($failedUsers as [$reason, $payload]) {
                            $payload = json_encode($payload);
                            $log->error("Failed to provision {$payload}: {$reason}");
                        }
                    } else {
                        // not an array: just dump the response body
                        $log->warning($r->getBody()->getContents());
                    }

                } else {
                    $log->critical('No parsable response received from server');
                }

            }
        } catch (Schoolbox\Mapper\Exception $e) {
            // failed to look up route
            $log->emergency($e->getMessage(), [ $e ]);
            return;

        } catch (RouteNotFound $e) {
            // route lookup failed: logic error
            $log->critical($e->getMessage(), [ $e ]);
            return;

        } catch (ClientException $e) {
            // client made a wobbly
            $log->warning($e->getMessage(), [ $e ]);

        } catch (ServerException $e) {
            // server did a boo boo
            $log->error($e->getMessage(), [ $e ]);
        }
    }


    /**
     * @param string $routeName
     * @param array  $query
     * @throws RouteNotFound
     * @throws RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @return string
     */
    private function getFromHelp($routeName, array $query = [])
    {
        $uri = $this->router->generate(
            "helpCourseParticipation.{$routeName}",
            [ 'schoolCode' => $this->schoolCode ]
        );

        $query = array_merge($query, $this->generateAuth());

        return $this->http->get($uri, [ 'query' => $query ])->getBody()->getContents();
    }


    /**
     * @param string $routeName
     * @param array  $payload
     * @param array  $query
     * @throws RouteNotFound
     * @throws RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @return string
     */
    private function postToHelp($routeName, array $payload, array $query = [])
    {
        $uri = $this->router->generate(
            "helpCourseParticipation.{$routeName}",
            [ 'schoolCode' => $this->schoolCode ]
        );

        $query = array_merge($query, $this->generateAuth());

        return $this->http->post($uri, [
            'query'       => $query,
            'form_params' => $payload
        ])->getBody()->getContents();
    }


    /**
     * @throws \Schoolbox\Mapper\Exception
     * @return array
     */
    private function generateAuth()
    {
        $now   = Alaress_Date_Format::now();
        $token = $this->handler->computeAuthDigest($this->schoolCode, $now);

        return [
            'ts'    => $now->getUnixTimeStamp(),
            'token' => $token
        ];
    }
});

