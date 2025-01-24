<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Admin\Config;

use Alaress\Web\Controller\ConfigController;
use Schoolbox\Config\ConfigMapper;

/**
 * Controller for realtime admin config settings.
 */
class Controller extends ConfigController
{
    /**
     * Updates configuration for realtime notification related config settings.
     *
     * @api POST /adminv2/notifications/config
     * @since 25.0
     */
    public function postConfig(): void
    {
        $this->updateConfig(
            $this->requestPost->getArrayCopy(),
            $this->getConfigMapper()->getPredicateBuilder()->in(
                'name',
                [
                    ConfigMapper::NAME_NOTIFICATION_BLACKOUT_ENABLED,
                    ConfigMapper::NAME_NOTIFICATION_BLACKOUT_START_TIME,
                    ConfigMapper::NAME_NOTIFICATION_BLACKOUT_END_TIME,
                ]
            ),
        );
        $this->response = $this->responseEmpty();
    }
}
