<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Restore;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\UserMapper;
use Twig\Error\LoaderError;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'restore';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes()
    {
        // super only, they can restore more than just resources
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '', 'getIndex', $perms, null);
        $this->register('POST', '/folder', 'restoreFolder', $perms, null);
        $this->register('POST', '/assessment', 'restoreAssessment', $perms, null);
        $this->register('POST', '/user', 'restoreUser', $perms, null);
        $this->register('POST', '/courseSpec', 'restoreCourseSpec', $perms, null);
        $this->register('POST', '/courseImpl', 'restoreCourseImpl', $perms, null);
        $this->register('POST', '/unitSpec', 'restoreUnitSpec', $perms, null);
        $this->register('POST', '/unitImpl', 'restoreUnitImpl', $perms, null);
        $this->register('POST', '/file', 'restoreFile', $perms, null);
    }

    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
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
        return new Controller(
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(FolderCodeMapper::class),
            $this->getMapperFactory()->get(AssessmentMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(FileMapper::class),
            $this->getMapperFactory()->get(CourseSpecMapper::class),
            $this->getMapperFactory()->get(UnitSpecMapper::class),
            $this->getMapperFactory()->get(CourseImplMapper::class),
            $this->getMapperFactory()->get(UnitImplMapper::class),
        );
    }
}
