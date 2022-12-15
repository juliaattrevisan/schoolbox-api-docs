<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Test\Api;

use ApiTester;
use Schoolbox\Pastoral\Access\Mapper;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * evidenceFeed@user@{id}.yaml
 * /usr/share/schoolbox/vendor/bin/codecept run api EvidenceFeedUserCest
 *
 */
class EvidenceFeedUserCest
{
    public function testGet(\ApiTester $i)
    {
        $test = $i->createAssessment();
        return $test;
    }
}
