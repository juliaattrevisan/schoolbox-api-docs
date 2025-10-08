<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Alaress\Redis;
use External\Isams\Register\CacheHelper as IsamsCacheHelper;
use Schoolbox\Oidc\CacheHelper;

class Registry
{
    /**
     * Set key for old cache
     *
     * @deprecated This will eventually be removed
     */
    private const SET_KEY = '__set';

    /**
     * Template var for instance ID: this will match any instance ID in a Redis
     * key.
     */
    public const INSTANCE_ID_MATCH = '(?P<instanceId>\\w+)';

    /**
     * Recognised key patterns in Redis
     */
    public const KEY_PATTERNS = [
        // Cache-only patterns go here
        Redis::INSTANCE_CACHE   => [
            // Ccrub
            '^fc:' . self::INSTANCE_ID_MATCH . ':scrub.html:',
            '^fc:' . self::INSTANCE_ID_MATCH . ':scrub.htmlAbsoluteUri:',

            // Verso API
            '^fc:' . self::INSTANCE_ID_MATCH . ':verso_api:',

            // External
            '^fc:' . self::INSTANCE_ID_MATCH . ':External_',

            // vCal cache
            "^vcal:" . self::INSTANCE_ID_MATCH . ":[0-9a-f]{64}",

            // Locks
            '^lock:' . self::INSTANCE_ID_MATCH . ':',

            // Atlas API?
            '^atlasApi:' . self::INSTANCE_ID_MATCH . '.',

            // Aura route and module caches
            'routeCache:' . self::INSTANCE_ID_MATCH,
            'moduleCache:' . self::INSTANCE_ID_MATCH,

            // OIDC client cache, used for caching:
            // * OpenID configuration and JWKS responses from the configured
            //   oAuth/OIDC provider
            CacheHelper::BASE_CLIENT_KEY . '\\.' . self::INSTANCE_ID_MATCH . ':' . IsamsCacheHelper::REGISTERS_CACHE_KEY,
            CacheHelper::BASE_CLIENT_KEY . '\\.' . self::INSTANCE_ID_MATCH . ':',
            // Old keys: will eventually be removed
            CacheHelper::BASE_CLIENT_KEY . ':' . self::INSTANCE_ID_MATCH . ':isams_registers',
            CacheHelper::BASE_CLIENT_KEY . ':' . self::INSTANCE_ID_MATCH . ':' . self::SET_KEY,
            CacheHelper::BASE_CLIENT_KEY . ':' . self::INSTANCE_ID_MATCH . ':',

            // ClickView (Homepage Component) cache
            'component\\.homepage\\.clickview\\.' . self::INSTANCE_ID_MATCH . ':',
            // Old key
            'component.homepage.clickview:' . self::INSTANCE_ID_MATCH . ':' . self::SET_KEY,
            'component.homepage.clickview:' . self::INSTANCE_ID_MATCH . ':',

            // Paperly cache
            'paperly\\.' . self::INSTANCE_ID_MATCH . ':paperly_dashboard_user',
            // Old key
            'paperly' . ':' . self::INSTANCE_ID_MATCH . ':paperly_dashboard_user',
            'paperly' . ':' . self::INSTANCE_ID_MATCH . ':' . self::SET_KEY,

            // Storage cache
            '^fc:' . self::INSTANCE_ID_MATCH . ':system_usage_',

            // HTTP client cache
            'httpClient\\.' . self::INSTANCE_ID_MATCH . ':',
            // Old key
            'httpClient:' . self::INSTANCE_ID_MATCH . ':' . self::SET_KEY,
            'httpClient:' . self::INSTANCE_ID_MATCH . ':',
        ],
        // Storage-only patterns go here
        Redis::INSTANCE_STORAGE => [
            // Sessions
            '^PHPREDIS_SESSION:' . self::INSTANCE_ID_MATCH . ':',

            // Remember me
            "^remember:" . self::INSTANCE_ID_MATCH . ":[0-9a-f]{40}$",

            // Message instance, detailing the content of a message
            // (an `\Alaress\Async\Notification\Instance\Instance` instance)
            '^msg:' . self::INSTANCE_ID_MATCH . ':m:.+',

            // Message trigger, detailing when, how and why of a message
            // (a `\Schoolbox\Realtime\Schedule\Trigger\TriggerInterface`
            //  instance)
            '^msg:' . self::INSTANCE_ID_MATCH . ':s:.+',

            // Set of message keys relating to a particular object
            '^msg:' . self::INSTANCE_ID_MATCH . ':q:\D.+',

            // Set of message keys relating to a particular user
            '^msg:' . self::INSTANCE_ID_MATCH . ':q:\d+:unread',
            '^msg:' . self::INSTANCE_ID_MATCH . ':q:\d+:listed',
            '^msg:' . self::INSTANCE_ID_MATCH . ':q:\d+:digest',
            '^msg:' . self::INSTANCE_ID_MATCH . ':q:\d+:mobile',
            // Not sure if the below are used
            //            '^msg:' . self::INSTANCE_ID_MATCH . ':q:\d+:push',
            //            '^msg:' . self::INSTANCE_ID_MATCH . ':q:\d+:alert',

            // Message route cache
            "^m:" . self::INSTANCE_ID_MATCH . ":routes",

            // Scheduled triggers?
            // (Don't know if this is even used, or where it should be if it is)
            "^schedule:" . self::INSTANCE_ID_MATCH . ":index",
            "^schedule:" . self::INSTANCE_ID_MATCH . ":[0-9a-f]{40}",

            // Access tokens for LTI assessments
            '^accessToken\\.' . self::INSTANCE_ID_MATCH . ':.+',
            // Old key
            '^accessToken:' . self::INSTANCE_ID_MATCH . ':' . self::SET_KEY,
            '^accessToken:' . self::INSTANCE_ID_MATCH . ':.+',

            // Etherpad patterns
            '^pad:g\.',
            '^token2author:',
            '^author2sessions:',
            '^mapper2author:',
            '^group2sessions:g\.',
            '^sessionstorage:',
            '^globalAuthor:',
            '^ueberDB:keys:',
            '^pad2readonly:g\.',
            '^readonly2pad:r\.',
            '^group:g\.',
            '^session:s\.',
            "^groups$",
            '^expressSessionSecrets:',

            // OIDC server cache, used for caching:
            // * auth codes
            // * access tokens
            // * nonces
            // * refresh tokens
            CacheHelper::BASE_SERVER_KEY . '\\.' . self::INSTANCE_ID_MATCH . ':',
            // Old keys
            CacheHelper::BASE_SERVER_KEY . ':' . self::INSTANCE_ID_MATCH . ':' . self::SET_KEY,
            CacheHelper::BASE_SERVER_KEY . ':' . self::INSTANCE_ID_MATCH . ':',
        ],
        // Queue patterns go here
        // (Note that queue is the same instance as cache, so it's not an issue
        //  if queue patterns are found in cache and v.v.)
        //        Redis::INSTANCE_SIGNAL_QUEUE => [
        //            // Signal queues
        //            'scheduled.' . self::INSTANCE_ID_MATCH . '.*',
        //            'routed.' . self::INSTANCE_ID_MATCH . '.*',
        //        ],
    ];
}
