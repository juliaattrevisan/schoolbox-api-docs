<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\Firebase\ServiceWorker;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;

/**
 * Module for the Firebase service worker.
 *
 * Needs to be an Aura module+controller, as data from configuration is
 * required.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'firebaseServiceWorker';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        if ($this->getConfig()->get(ConfigMapper::NAME_WEB_PUSH_NOTIFICATIONS_ENABLED)) {
            $this->register('GET', 'firebase-messaging-sw.js', 'getServiceWorker');
        }
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }
}
