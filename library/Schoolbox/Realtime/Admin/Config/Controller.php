<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Admin\Config;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Predis\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;

/**
 * Controller for realtime admin config settings.
 */
class Controller extends ConfigController
{
    /**
     * Constructor
     *
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder            $rebuilder
     * @param CacheInterface       $oidcCache
     * @param ClientInterface|null $signalQueue          The signal queue for informing the mobile daemon that settings
     *                                                   have changed.
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private ?ClientInterface $signalQueue = null,
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }


    /**
     * Updates configuration for realtime notification related config settings.
     *
     * @api POST /adminv2/notifications/config
     * @since 25.0
     */
    public function postConfig(): void
    {
        // These settings should restart the mobile daemon
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

        // Reload the mobile daemon
        $this->signalQueue?->publish('mobiled.' . INSTANCE_ID . '.refreshConfiguration', '');

        // Respond with nothing?
        $this->response = $this->responseEmpty();
    }
}
