<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Resource\Storage\FilesystemProxy;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminBulkFeedbackImport';


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
        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->injector->get('assessmentDataSource');

        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->injector->get('filesystem.storageProxy');

        return new Controller(
            $assessmentDataSource,
            $filesystemProxy,
            $this->getMapperFactory()->get(\Schoolbox\Error\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\WorkType\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\UserMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Resource\Folder\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Resource\Storage\Mapper::class),
            $this->getMapperFactory()->get(SubmissionBoxMapper::class),
            $this->getMapperFactory()->get(ReturnedWorkMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\DueWork\StateMapper::class),
            $this->injector->get('feedbackDataSource'),
            $this->injector->get('l10n'),
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
