<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Digistorm;

use Alaress\Async\Notification\Cloud\AbstractService;
use Schoolbox\MobileApp\Registry;

/**
 * Digistorm notification service
 */
class Service extends AbstractService
{
    /**
     * @inheritdoc
     */
    public function getProviderName(): string
    {
        return Registry::PROVIDER_DIGISTORM;
    }

    /**
     * @inheritdoc
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0)
    {
        // TODO: Implement propagate() method.
        return true;
    }
}
