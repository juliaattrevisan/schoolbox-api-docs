<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Monolog\Logger;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\ClientRepository;
use Predis\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\LTI\AccessToken\Codec as LtiAccessTokenCodec;
use Schoolbox\Oidc\CacheHelper;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI tool for migrating all values which were stored using the old Redis cache
 * library in the storage instance, to keys and values which are recognised by
 * the Symfony cache library's Redis adapter.
 *
 * This includes:
 * * LTI access tokens
 * * OIDC auth codes and access/refresh/ID tokens
 */
$app = new class extends CliTool\App {
    /**
     * Set key, as used by the old Redis cache.
     *
     * This is intended to store all keys within the namespace of the cache, so
     * that they may be easily iterated.
     */
    private const SET_KEY = '__set';

    /**
     * Run the tool
     *
     * @return void
     */
    public function run(): void
    {
        /** @var ClientInterface $redisClient */
        $redisClient = $this->getDi()->get('redis.storage');

        /** @var RegistrationRepositoryInterface $registrationRepository */
        $registrationRepository = $this->getDi()->get('lti.registrations');
        $ltiAccessTokenCodec    = new LtiAccessTokenCodec(new ClientRepository($registrationRepository));

        /**
         * Migrations included in this script.
         *
         * @var array<array{
         *      name:         string,
         *      oldNamespace: string,
         *      newNamespace: string,
         *      codec:        ICodec,
         *      cache:        CacheInterface,
         * }> $migrations
         */
        $migrations = [
            [
                'name'         => 'LTI access tokens',
                'oldNamespace' => 'accessToken:' . INSTANCE_ID,
                'newNamespace' => 'accessToken.' . INSTANCE_ID,
                'codec'        => $ltiAccessTokenCodec,
                'cache'        => new Psr16Cache($this->getDi()->get('lti.accessTokenCache')),
            ],
            [
                'name'         => 'OIDC server auth codes, access/refresh/ID tokens',
                'oldNamespace' => CacheHelper::BASE_SERVER_KEY . ':' . INSTANCE_ID,
                'newNamespace' => CacheHelper::BASE_SERVER_KEY . '.' . INSTANCE_ID,
                'codec'        => new Base64(),
                'cache'        => new Psr16Cache(
                    new RedisAdapter(
                        $redisClient,
                        CacheHelper::BASE_SERVER_KEY . '.' . INSTANCE_ID,
                    )
                ),
            ],
        ];

        // Find all keys in the old cache, and migrate to the new cache.
        // TODO consider a keyspace scan instead of the set?
        // TODO consider whether old sets need to be removed?
        foreach ($migrations as $migration) {
            $this->getLogger()->info(
                sprintf(
                    'Running migration for %s (%s => %s)',
                    $migration['name'],
                    $migration['oldNamespace'],
                    $migration['newNamespace'],
                ),
            );
            $keys = $redisClient->smembers($migration['oldNamespace'] . ':' . self::SET_KEY);
            $this->getLogger()->info(
                sprintf(
                    '. %d keys found in %s:__set',
                    count($keys),
                    $migration['oldNamespace'],
                ),
            );

            // Set each key, for whatever its remaining TTL is
            foreach ($keys as $oldKey) {
                $this->getLogger()->debug('.. migrating cache key: ' . $oldKey);
                $value = $redisClient->get($oldKey);
                if (!isset($value)) {
                    $this->getLogger()->warning(
                        sprintf('... cache key %s value does not exist: skipping', $oldKey)
                    );
                    continue;
                }

                // Don't expire if the cached item was never set to expire
                $ttl = $redisClient->ttl($oldKey);
                if ($ttl < 0) {
                    $ttl = null;
                    $this->getLogger()->debug('... key found (no ttl)');
                } else {
                    $this->getLogger()->debug(sprintf('... key found (ttl: %d)', $ttl));
                }

                // Decode the value: if that fails, something went wrong
                try {
                    $decodedValue = $migration['codec']->decode($value);
                } catch (Throwable $e) {
                    $this->getLogger()->warning(
                        sprintf(
                            '... cache key %s failed to decode (message: %s): skipping',
                            $oldKey,
                            $e->getMessage(),
                        ),
                    );
                    continue;
                }

                // Remove the old namespace from the key, then set its value
                $newKey = str_replace($migration['oldNamespace'] . ':', '', $oldKey);
                if ($migration['cache']->set($newKey, $decodedValue, $ttl)) {
                    $this->getLogger()->debug('... key migrated');
                } else {
                    $this->getLogger()->warning(
                        sprintf('... cache key %s failed to migrate', $oldKey)
                    );
                }

                // TODO delete from old cache
            }

            // TODO if deleting from old cache, delete set as well
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Logger::INFO], true))->runApp();
