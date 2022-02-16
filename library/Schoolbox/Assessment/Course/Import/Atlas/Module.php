<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Course\Import\Atlas\Api\BridgeFactory;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Twig\Error\LoaderError;

class Module extends \Alaress\Module\Module
{
    /**
     * Is attachment import enabled?
     *
     * Keep it disabled until/unless the Atlas API allows file download with
     * the credentials provided to it.
     */
    public const ENABLE_ATTACHMENT_IMPORT = false;


    /** @var string */
    protected $basename = 'courseImportAtlas';


    /**
     * @return void
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $staffOnly = new Rule\All(
            new Rule\IsConfigOn('rubicon_atlas_api_token', $this->getConfig()),
            $this->getAuthRule('isStaffOrSuper')
        );
        $this->register('GET', '', 'getImport', $staffOnly);
        $this->register('POST', '', 'postImport', $staffOnly);
        $this->register('GET', '/curriculum/list', 'getCurriculumList', $staffOnly, null);
        $this->register('GET', '/curriculum/{curriculumId:\d+}', 'getCurriculum', $staffOnly, null);
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
            (new BridgeFactory(
                $this->getConfig()->get('rubicon_atlas_api_token'),
                $this->injector->get('redis.cache')
            ))->build(),
            (new ImporterFactory(
                $this->injector->get('user'),
                $this->getConfig(),
                $this->getMapperFactory(),
                $this->injector->get('assessmentDataSource'),
                $this->getMapperFactory()->get(RubricDataSource::class)
            ))->build(),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(CourseSpecMapper::class),
        );
    }
}
