<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Admin\Config;

use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Module\Module as BaseModule;
use Alaress\Redis;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

/**
 * Module for realtime admin config settings.
 */
class Module extends BaseModule
{
    protected $basename = 'adminNotificationsConfig';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $this->register(self::METHOD_POST, '', 'postConfig', new IsSuperuser());
    }


    /**
     * @return BaseController
     */
    public function buildController(): BaseController
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
            Redis::getSignalQueueClientInstance(),
        );
    }
}
