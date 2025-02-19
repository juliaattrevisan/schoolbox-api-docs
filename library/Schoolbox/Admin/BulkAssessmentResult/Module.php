<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkAssessmentResult;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminBulkAssessmentResult';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only superusers are allowed
         */
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '', 'getIndex', $perms);
        $this->register('POST', '/preview', 'postPreview', $perms);
        $this->register('POST', '/perform', 'postPerform', $perms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Error\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\WorkType\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\UserMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Resource\Folder\Mapper::class),
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
