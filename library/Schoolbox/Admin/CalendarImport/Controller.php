<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
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
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Url\Uri;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Bulk_Calendar;

/**
 * Class Controller
 *
 * Bulk import calendar in admin page
 *
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
        $currentUrl  = $this->routeGenerator->generate('adminCalendarImport.getCalendarImportForm');

        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/index.twig',
            [
                'pageCurrent'    => $currentUrl,
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs($currentUrl),
                'attachzone'     => $this->getAttachZone(),
                // Encode return URL separately, so that page number is included
                'encodedReturnUrl' => Redirect::encode((new Uri($currentUrl))),
            ],
        );
    }

    public function postRunCalendarImport(): void
    {
        $this->extendExecutionTime(3600);
        $post = $this->requestPost;

        $file = $post->get('csvFilePath');
        $ownerId = $post->get('userId');

        if (!$file) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $bulkHandler = new Bulk_Calendar(
            $file,
            null,
            $ownerId,
            [],
            [],
            [],
        );
        $allEvents = $bulkHandler->addCalendar(true);

        $currentUrl = $this->routeGenerator->generate('adminCalendarImport.getCalendarImportForm');
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/importResults.twig',
            [
                'pageCurrent' => $currentUrl,
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs(
                        $currentUrl
                    ),
                'perform' => 1,
                'allEvents' => $allEvents,
                'returnUrl' => $this->requestQuery->get('returnUrl')
                    ?? $currentUrl,
            ]
        );
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
        $allEvents = $bulkHandler->addCalendar(false);

        $currentUrl = $this->routeGenerator->generate('adminCalendarImport.getCalendarImportForm');
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/importResults.twig',
            [
                'pageCurrent' => $currentUrl,
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs(
                        $currentUrl
                    ),
                'allEvents' => $allEvents,
                'userId' => $ownerId,
                'csvFilePath' => $fileTempName,
                'returnUrl' => $this->requestQuery->get('returnUrl')
                    ?? $currentUrl,
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
