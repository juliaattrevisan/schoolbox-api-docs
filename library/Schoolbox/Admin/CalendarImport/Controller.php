<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\CalendarImport;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Calendar\Event\Type\EventBase;
use League\Flysystem\FilesystemException;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\CampusMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Url\Uri;
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
     *  Controller constructor.
     *
     * @param ErrorMapper      $errorMapper
     * @param CollectorFactory $collectorFactory
     * @param FilesystemProxy  $filesystemProxy
     * @param CampusMapper     $campusMapper
     * @param RoleMapper       $roleMapper
     * @param StorageMapper    $storageMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private CollectorFactory $collectorFactory,
        private FilesystemProxy $filesystemProxy,
        private CampusMapper $campusMapper,
        private RoleMapper $roleMapper,
        private StorageMapper $storageMapper,
    ) {
    }

    /**
     * Display bulk upload admin page
     *
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws \ReflectionException
     * @since 25.0.0
     * @api   GET /adminv2/calendar/import/school-events
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

    /**
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws \League\Flysystem\FilesystemException
     * @throws \Twig\Error\Error
     *
     * @since 25.0.0
     * @api   POST /adminv2/calendar/import/school-events/run
     */
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

        // Get the local path to the file for use in Bulk_Calendar.
        // Note this local file may not be available in the next request to postRunCalendarImport(), because the next
        // request may be served by a different Docker container.  That is why we pass the file's hash to the Twig
        // template below.  This will allow postRunCalendarImport() to retrieve the file from storage, using the hash.
        $localPathToFile = $this->filesystemProxy->localPath($file);

        $bulkHandler = new Bulk_Calendar(
            $localPathToFile,
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
                'csvFileHash' => $file->getHash(), // Pass file hash so the file can be retrieved at the next stage of the import.
                'returnUrl' => $this->requestQuery->get('returnUrl')
                    ?? $currentUrl,
            ]
        );
    }

    /**
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws \Twig\Error\Error
     * @throws FilesystemException
     *
     * @since 25.0.0
     * @api   POST /adminv2/calendar/import/school-events/run
     */
    public function postRunCalendarImport(): void
    {
        $this->extendExecutionTime(3600);
        $post = $this->requestPost;

        $csvFileHash = $post->get('csvFileHash');
        $ownerId = $post->get('userId');

        if (!$csvFileHash) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        try {
            $file = $this->storageMapper->findByHash($csvFileHash);
            $localPathToFile = $this->filesystemProxy->localPath($file); // Get the local path to the file for use in Bulk_Calendar.
        } catch (MapperException | FilesystemException) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_IMPORT_FILE_NOT_FOUND);
        }

        $bulkHandler = new Bulk_Calendar(
            $localPathToFile,
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

    /**
     * Display bulk upload admin page
     *
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws \ReflectionException
     * @since 25.0.0
     * @api   GET /adminv2/calendar/import/group-events
     */
    public function getCalendarGroupEventsImportForm(): void
    {
        $currentUrl  = $this->routeGenerator->generate('adminCalendarImport.getCalendarGroupEventsImportForm');

        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/GroupEvent/index.twig',
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

    /**
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws \League\Flysystem\FilesystemException
     * @throws \Twig\Error\Error
     *
     * @since 25.0.0
     * @api   POST /adminv2/calendar/import/group-events
     */
    public function postCalendarGroupEventsImport(): void
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

        // Note this local file may not be available in the next request to postRunGroupEventsImport(), because the next
        // request may be served by a different Docker container.  That is why we pass the file's hash to the Twig
        // template below.  This will allow postRunGroupEventsImport() to retrieve the file from storage, using the hash.
        $localPathToFile = $this->filesystemProxy->localPath($file);

        $bulkHandler = new Bulk_Calendar(
            $localPathToFile,
            null,
            $ownerId,
            [],
            [],
            [],
        );
        $allEvents = $bulkHandler->addCalendar(false);

        $currentUrl = $this->routeGenerator->generate('adminCalendarImport.getCalendarGroupEventsImportForm');
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/GroupEvent/importResults.twig',
            [
                'pageCurrent' => $currentUrl,
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs(
                        $currentUrl
                    ),
                'allEvents' => $allEvents,
                'userId' => $ownerId,
                'csvFileHash' => $file->getHash(),
                'returnUrl' => $this->requestQuery->get('returnUrl')
                    ?? $currentUrl,
            ]
        );
    }

    /**
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws \Twig\Error\Error
     *
     * @since 25.0.0
     * @api   GET /adminv2/calendar/import/group-events/run
     */
    public function postRunCalendarGroupEventsImport(): void
    {
        $this->extendExecutionTime(3600);
        $post = $this->requestPost;

        $csvFileHash = $post->get('csvFileHash');
        $ownerId = $post->get('userId');

        if (!$csvFileHash) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        try {
            $file = $this->storageMapper->findByHash($csvFileHash);
            $localPathToFile = $this->filesystemProxy->localPath($file); // Get the local path to the file for use in Bulk_Calendar.
        } catch (MapperException | FilesystemException) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_IMPORT_FILE_NOT_FOUND);
        }

        $bulkHandler = new Bulk_Calendar(
            $localPathToFile,
            null,
            $ownerId,
            [],
            [],
            [],
        );
        $allEvents = $bulkHandler->addCalendar(true);

        $currentUrl = $this->routeGenerator->generate('adminCalendarImport.getCalendarGroupEventsImportForm');
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/GroupEvent/importResults.twig',
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

    /**
     * Display bulk iCalendar upload admin page
     *
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws \ReflectionException
     *
     * @since 25.0.0
     * @api   GET /adminv2/calendar/import/icalendar-events
     */
    public function getCalendarVcalImportForm(): void
    {
        $currentUrl  = $this->routeGenerator->generate('adminCalendarImport.getCalendarVcalImportForm');

        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/VCalEvent/index.twig',
            [
                'pageCurrent'    => $currentUrl,
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs($currentUrl),
                'attachzone'     => $this->getAttachZone(EventBase::RENDER_FORMAT_VCAL),
                // Encode return URL separately, so that page number is included
                'encodedReturnUrl' => Redirect::encode((new Uri($currentUrl))),
                'campusList' => $this->campusMapper->findAll(['deleted' => false, 'order' => [ 'sequence' => 'asc']]),
                'roleList' => $this->roleMapper->findAll(['deleted' => false, 'order' => [ 'name' => 'asc']]),
            ],
        );
    }

    /**
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws \League\Flysystem\FilesystemException
     * @throws \Twig\Error\Error
     *
     * @since 25.0.0
     * @api   POST /adminv2/calendar/import/icalendar-events
     */
    public function postCalendarVcalImportForm(): void
    {
        $this->extendExecutionTime(3600);
        $post = $this->requestPost;

        $attachment = $post->get('bulkadd');
        $campusList = $post->get('campusList', []);
        $roleList = $post->get('userGroup', []);
        $ownerId = $post->get('userid');

        if (!$attachment || empty($roleList) || empty($campusList)) {
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
            $roleList,
            $campusList,
        );
        $allEvents = $bulkHandler->addCalendar(false);

        $currentUrl = $this->routeGenerator->generate('adminCalendarImport.getCalendarVcalImportForm');
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/VCalEvent/importResults.twig',
            [
                'pageCurrent' => $currentUrl,
                'breadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))
                    ->generateBreadcrumbs(
                        $currentUrl
                    ),
                'allEvents' => $allEvents,
                'userId' => $ownerId,
                'icsFilePath' => $fileTempName,
                'campusList' => json_encode($campusList),
                'roleList' => json_encode($roleList),
                'returnUrl' => $this->requestQuery->get('returnUrl')
                    ?? $currentUrl,
            ]
        );
    }

    /**
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws RouteNotFound
     * @throws \Twig\Error\Error
     *
     * @since 25.0.0
     * @api   POST /adminv2/calendar/import/icalendar-events/run
     */
    public function postRunCalendarVcalImportForm(): void
    {
        $this->extendExecutionTime(3600);
        $post = $this->requestPost;

        $file = $post->get('icsFilePath');
        $campusList = json_decode($post->get('campusList', ''), true) ?? [];
        $roleList = json_decode($post->get('userGroup', ''), true) ?? [];
        $ownerId = $post->get('userId');

        if (!$file || empty($roleList) || empty($campusList)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $bulkHandler = new Bulk_Calendar(
            $file,
            null,
            $ownerId,
            [],
            $roleList,
            $campusList,
        );
        $allEvents = $bulkHandler->addCalendar(true);

        $currentUrl = $this->routeGenerator->generate('adminCalendarImport.getCalendarVcalImportForm');
        $this->response = $this->responseFromTemplate(
            '@adminCalendarImport/VCalEvent/importResults.twig',
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

    /**
     * Insert an attach zone into the template
     *
     * @param string $type type of file to accept (ie. csv, ics)
     * @return array
     */
    private function getAttachZone(string $type = EventBase::RENDER_FORMAT_CSV): array
    {
        $zone = (new Zone('bulkadd'))
            ->setFileVar('bulkadd[]')
            ->setThumbnailSize(64)
            ->setAttachmentsLabel('')
            ->setReadOnly(false)
            ->setMulti(false)
            ->useImportExpiryDate();

        if (EventBase::RENDER_FORMAT_VCAL === $type) {
            $zone
                ->setModalTitle('Select ICS')
                ->setAttachPrompt('Upload ICS')
                ->setMimeFilter('.ics,text/calendar');
        } else {
            $zone
                ->setModalTitle('Select CSV')
                ->setAttachPrompt('Upload CSV')
                ->setMimeFilter('.csv,text/csv');
        }

        return $zone->getTwigContext();
    }
}
