<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Config\Field\Mapper as FieldMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminDesignSkinConfig';

    protected function registerRoutes(): void
    {
        /* Superusers or those with Design Full Access can access this module
         */
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasServicePermission(
                    UserAccess::SERVICE_DESIGN,
                    UserAccess::ACCESS_FULL
                ),
                new Rule\IsSuperuser()
            ),
        );

        $this->register('GET', '', 'getConfig', $perms);
        $this->register('POST', '', 'postConfig', $perms);
        $this->register('POST', '/preview', 'previewCss', $perms);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            $this->getMapperFactory()->get(FieldMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
            $this->injector->get('templateEngine'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
