<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
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
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'folderMapper'     => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'courseSpecMapper' => $this->getMapperFactory()->getLazy(CourseSpecMapper::class),
                    'apiBridge'        => $injector->lazy(
                        function () use ($injector) {
                            return (new BridgeFactory(
                                $this->getConfig()->get('rubicon_atlas_api_token'),
                                $injector->get('redis')
                            ))->build();
                        }
                    ),
                    'importer'         => $injector->lazy(
                        function () use ($injector) {
                            return (new ImporterFactory(
                                $injector->get('user'),
                                $this->getConfig(),
                                $this->getMapperFactory(),
                                $injector->get('assessmentDataSource'),
                                RubricDataSource::getInstance()
                            ))->build();
                        }
                    ),
                ]
            )
        );
    }
}
