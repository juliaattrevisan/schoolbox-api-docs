<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Alaress\Redis;

class Registry
{
    /**
     * Recognised key patterns in Redis
     */
    public const KEY_PATTERNS = [
        // Cache-only patterns go here
        Redis::INSTANCE_CACHE   => [
            // Old and new scrub
            '^scrub.html:',
            '^scrub.htmlAbsoluteUri:',
            '^fc:' . INSTANCE_ID . ':scrub.html:',
            '^fc:' . INSTANCE_ID . ':scrub.htmlAbsoluteUri:',

            // Old and new Verso API
            '^verso_api',
            '^fc:' . INSTANCE_ID . ':verso_api:',

            // Old and new external
            "^External_.+",
            '^fc:' . INSTANCE_ID . ':External_',

            // vCal cache: not partitioned by instance ID
            // (it may need to be one day)
            "^vcal.[0-9a-f]{64}",

            // Locks
            '^lock:' . INSTANCE_ID . ':',

            // Atlas API?
            '^atlasApi:' . INSTANCE_ID . '.',

            // Aura route and module caches
            'routeCache:' . INSTANCE_ID,
            'moduleCache:' . INSTANCE_ID,

            // OIDC cache
            'oidc:' . INSTANCE_ID . ':' . Redis\Cache\ItemPool::SET_KEY,
            'oidc:' . INSTANCE_ID . ':',
        ],
        // Storage-only patterns go here
        Redis::INSTANCE_STORAGE => [
            // Sessions
            '^PHPREDIS_SESSION:',

            // Old and new remember me
            "^[0-9a-f]{40}$",
            "^remember:" . INSTANCE_ID . ":[0-9a-f]{40}$",

            // Message instance, detailing the content of a message
            // (an `\Alaress\Async\Notification\Instance\Instance` instance)
            '^msg:' . INSTANCE_ID . ':m:.+',

            // Message trigger, detailing when, how and why of a message
            // (a `\Schoolbox\Realtime\Schedule\Trigger\TriggerInterface`
            //  instance)
            '^msg:' . INSTANCE_ID . ':s:.+',

            // Set of message keys relating to a particular object
            '^msg:' . INSTANCE_ID . ':q:\D.+',

            // Set of message keys relating to a particular user
            '^msg:' . INSTANCE_ID . ':q:\d+:unread',
            '^msg:' . INSTANCE_ID . ':q:\d+:listed',
            '^msg:' . INSTANCE_ID . ':q:\d+:digest',
            // Not sure if the below are used
            //            '^msg:' . INSTANCE_ID . ':q:\d+:push',
            //            '^msg:' . INSTANCE_ID . ':q:\d+:alert',

            // Message route cache
            "^m:" . INSTANCE_ID . ":routes",

            // Scheduled triggers?
            // (Don't know if this is even used, or where it should be if it is)
            "^schedule:" . INSTANCE_ID . ":index",
            "^schedule:" . INSTANCE_ID . ":[0-9a-f]{40}",

            // Access tokens for LTI assessments
            '^accessToken:' . INSTANCE_ID . ':.+',

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
        ],
        // Queue patterns go here
        // (Note that queue is the same instance as cache, so it's not an issue
        //  if queue patterns are found in cache and v.v.)
        //        Redis::INSTANCE_SIGNAL_QUEUE => [
        //            // Signal queues
        //            'scheduled.' . INSTANCE_ID . '.*',
        //            'routed.' . INSTANCE_ID . '.*',
        //        ],
    ];
}
