<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Alaress\Authorizer\Rule;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'settings';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $rule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL)
            )
        );

        $this->register('GET', '/search', 'searchSettings', $rule);
        $this->register('GET', '{/typeLabel}', 'getSettings', $rule);
        $this->register('POST', '/{typeLabel}', 'postSettings', $rule);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
        );
    }
}
