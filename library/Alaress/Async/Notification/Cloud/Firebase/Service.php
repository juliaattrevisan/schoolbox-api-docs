<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Firebase;

use Alaress\Async\Notification\Cloud\AbstractService;
use Kreait\Firebase\Factory;
use Schoolbox\MobileApp\Registry;

/**
 * Firebase-based notification service.
 */
class Service extends AbstractService
{
    /**
     * @inheritDoc
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0)
    {
        $factory = (new Factory());

        $messageClient = $factory->createMessaging();
    }

    /**
     * @inheritDoc
     */
    public function getProviderName()
    {
        return Registry::PROVIDER_FIREBASE;
    }
}
