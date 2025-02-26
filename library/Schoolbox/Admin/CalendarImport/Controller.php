<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\CalendarImport;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Import\Profile\Reader;
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
use Bulk_Calendar;

/**
 * Class Controller
 *
 * Bulk import courses to classes admin page
 *
 * @package Schoolbox\Admin\BulkCourseImport
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private CollectorFactory $collectorFactory,
        private FilesystemProxy $filesystemProxy,
    ) {
    }


    /**
     * Display bulk upload admin page
     *
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws \ReflectionException
     * @since 16.0.0
     * @api   GET /adminv2/calendar/Import
     */
    public function getCalendarImportForm(): void
    {
        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/index.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('adminCalendarImport.getCalendarImportForm'),
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs(
                        $this->routeGenerator->generate('adminCalendarImport.getCalendarImportForm')
                    ),
                'attachzone'     => $this->getAttachZone(),
            ],
        );
    }

    public function postCalendarImport1(): void
    {
        $this->extendExecutionTime(3600);
        $post = $this->requestPost;

        $attachment = $post->get('bulkadd');
        $ownerId = $post->get('userid');

        if (!$attachment) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $collector = $this->collectorFactory->build($attachment);

        try {
            $file = $collector->getFirst();
        } catch (\Schoolbox\Resource\Exception $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

//        $handle = $this->filesystemProxy->readStream($file);
//        $handle = (new \Alaress\Csv\ValidCSV($handle))->skipBom();
//        var_dump(fgetcsv($handle));
    }

    public function postCalendarImport(): void
    {
        $this->extendExecutionTime(3600);
        $post = $this->requestPost;

        $attachment = $post->get('bulkadd');
        $ownerId = $post->get('userid');

        if (!$attachment) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $collector = $this->collectorFactory->build($attachment);

        try {
            $file = $collector->getFirst();
        } catch (\Schoolbox\Resource\Exception $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $fileTempName    = sb_tempnam('cal');
        $fp              = fopen($fileTempName, 'wb');
        stream_copy_to_stream($this->filesystemProxy->readStream($file), $fp);
        fclose($fp);

        $bulkHandler = new Bulk_Calendar(
            $fileTempName,
            null,
            $ownerId,
            [],
            [],
            [],
        );
        $allEvents = $bulkHandler->addCalendar();

        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/preview.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('adminCalendarImport.getCalendarImportForm'),
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs(
                        $this->routeGenerator->generate('adminCalendarImport.getCalendarImportForm')
                    ),
                'allEvents'      => $allEvents,
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

        $post = $this->requestPost;

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
        $this->response = $this->responseFromTemplate(
            '@adminBulkCourseImport/importResults.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('adminBulkCourseImport.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkCourseImport.getIndex')),
                'results'        => $imported,
                'perform'        => $performMode,
            ],
        );
    }


    /**
     * Insert an attach zone into the template
     *
     * @return array
     */
    private function getAttachZone(): array
    {
        $zone = (new Zone('bulkadd'))
            ->setFileVar('bulkadd[]')
            ->setThumbnailSize(64)
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload CSV')
            ->setMimeFilter('.csv,text/csv')
            ->setReadOnly(false)
            ->setMulti(false)
            ->useImportExpiryDate();

        return $zone->getTwigContext();
    }
}
