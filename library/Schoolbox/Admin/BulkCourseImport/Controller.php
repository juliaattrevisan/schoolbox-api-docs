<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkCourseImport;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Assessment\Course\Import\CsvImporter;
use Schoolbox\Assessment\Course\Import\ImportFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * Bulk import courses to classes admin page
 *
 * @package Schoolbox\Admin\BulkCourseImport
 */
class Controller extends Base
{


    private FolderMapper $folderMapper;

    private UserMapper $userMapper;

    private CourseImplementation\Mapper $courseImplementationMapper;

    private UnitSpecification\Mapper $unitSpecificationMapper;

    private ImportFactory $importFactory;

    private ErrorMapper $errorMapper;

    private MapperProxy $assessmentMapperProxy;

    /**
     * Controller constructor.
     */
    public function __construct(
        FolderMapper $folderMapper,
        UserMapper $userMapper,
        CourseImplementation\Mapper $courseImplementationMapper,
        UnitSpecification\Mapper $unitSpecificationMapper,
        ImportFactory $importFactory,
        MapperProxy $assessmentMapperProxy,
        ErrorMapper $errorMapper,
        private FilesystemProxy $filesystemProxy,
    ) {
        $this->folderMapper               = $folderMapper;
        $this->userMapper                 = $userMapper;
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->unitSpecificationMapper    = $unitSpecificationMapper;
        $this->importFactory              = $importFactory;
        $this->assessmentMapperProxy      = $assessmentMapperProxy;
        $this->errorMapper                = $errorMapper;
    }


    /**
     * Display bulk upload admin page
     *
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws \ReflectionException
     * @since 16.0.0
     * @api   GET /adminv2/bulkCourseImport
     */
    public function getIndex(): void
    {
        // Render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@adminBulkCourseImport/index.twig',
                'data'     => [
                    'pageCurrent'    => $this->router->generate('adminBulkCourseImport.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminBulkCourseImport.getIndex')),
                    'attachzone'     => $this->getAttachZone(),
                ],
            ]
        );
    }

    /**
     * Import courses and render results
     *
     * @throws MapperException
     * @throws RouteNotFound
     * @throws ResourceException
     * @since 22.0.0
     * @api   POST /adminv2/bulkCourseImport
     */
    public function postImportResults(): void
    {
        $this->extendExecutionTime(3600);

        $post = $this->getRequest()->post;

        if (!\is_array($post->get('attachment'))) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $factory = new CollectorFactory();
        $csvFile = $factory->build($post->get('attachment'))->getFirst();

        $performMode    = (bool)$post->get('perform');
        $defaultDueTime = $this->getConfigLoader()->get('lms_duetime_default');

        $imported = (new CsvImporter(
            $this->folderMapper,
            $this->userMapper,
            $this->courseImplementationMapper,
            $this->unitSpecificationMapper,
            $this->importFactory,
            $this->assessmentMapperProxy,
        ))->import($this->filesystemProxy->readStream($csvFile), $defaultDueTime, $performMode);

        // Render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@adminBulkCourseImport/importResults.twig',
                'data'     => [
                    'pageCurrent'    => $this->router->generate('adminBulkCourseImport.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminBulkCourseImport.getIndex')),
                    'results'        => $imported,
                    'perform'        => $performMode,
                ],
            ]
        );
    }


    /**
     * Insert an attach zone into the template
     *
     * @return array
     */
    private function getAttachZone(): array
    {
        $zone = (new Zone('attachment'))
            ->setThumbnailSize(64)
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload CSV')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(false);

        return $zone->getTwigContext();
    }
}
