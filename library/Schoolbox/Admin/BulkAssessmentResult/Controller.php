<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkAssessmentResult;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use League\Flysystem\FilesystemException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\SummativeReport\Payload\VictorianProgression\Attendance\Processor;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Constructor.
     */
    public function __construct(
        private MapperProxy $assessmentMapperProxy,
        private FilesystemProxy $filesystemProxy,
        private \Schoolbox\Error\Mapper $errorMapper,
        private \Schoolbox\Learning\Assessment\WorkType\Mapper $worktypeMapper,
        private \Schoolbox\UserMapper $userMapper,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
    ) {
    }


    /**
     * Provide input form for configuration and async upload for the csv file
     * Also link to a csv template that users can download and follow
     */
    public function getIndex(): void
    {
        $zone = (new Zone('sourceFile'))
            ->setThumbnailSize(64)
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload CSV')
            ->setMimeFilter('.csv,text/csv')
            ->setReadOnly(false)
            ->setMulti(false)
            ->useImportExpiryDate();

        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkAssessmentResult.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkAssessmentResult.getIndex')),
                'worktypes' => $this->worktypeMapper->findAll()->sortNaturally('sequence'),
                /* Not sure if there's a shortcut to this: never seen one?
                 */
                'roletypeStaff' => \Schoolbox\Role::STAFF,
                'sourceFileZone' => $zone->getTwigContext(),
            ],
            'Bulk Assessment Result'
        );
    }


    /**
     * Process in planning mode
     *
     * @throws ContainerExceptionInterface
     * @throws Error
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws RouteNotFound
     * @throws FilesystemException
     */
    public function postPreview(): void
    {
        $settings = $this->verifySettings($this->requestPost->getArrayCopy());

        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

        /* This can be ported to the ui version with null and true in place of logger, perform and lock.
         */
        $outcome = (new Processor(
            $this->assessmentMapperProxy,
            $filesystemProxy,
            $this->userMapper,
            $this->folderMapper,
            null,
            $settings['worktype']->getId(),
            $settings['publishAt'],
            null,
            false,
        ))->parseFeedbacks($settings['sourceFile'], $settings['feedbackOwner'], null);

        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/preview.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkAssessmentResult.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkAssessmentResult.getIndex')),
                'settings' => $settings,
                'outcome' => $outcome
            ],
            'Bulk Assessment Result Preview',
        );
    }


    /**
     * Process in perform mode
     *
     * @throws Exception
     * @throws RouteNotFound
     * @throws Error
     */
    public function postPerform(): void
    {
        $settings = $this->verifySettings($this->requestPost->getArrayCopy());

        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/perform.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkAssessmentResult.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkAssessmentResult.getIndex')),
                'settings' => $settings,
            ],
            'Bulk Assessment Result',
        );
    }


    /**
     * Asserts that the selected worktype, feedback owner and csv file actually exists:
     * without those, we can't really continue
     *
     * Also assumes a publish datetime here, though you probably want this to be configurable
     * at some point in the future
     *
     * @param array $postVar
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    private function verifySettings(array $postVar): array
    {
        /* Worktype chosen must exist
         */
        $worktypeId = $postVar['worktypeId'] ?? null;

        try {
            $worktype = $this->worktypeMapper->findById($worktypeId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException('Unknown work type selected, or was omitted.');
        }

        /* Feedback Owner needs to exist
         */
        $feedbackOwnerId = $postVar['userId'] ?? null;

        try {
            $feedbackOwner = $this->userMapper->findById($feedbackOwnerId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException('Unknown feedback owner selected, or was omitted.');
        }

        /* Grab the storage file
         */
        $factory = new CollectorFactory();
        $csvFile = $factory->build($postVar['attachment'])->getFirst();

        if (!\is_array($postVar['attachment'])) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        return [
            'worktype' => $worktype,
            'feedbackOwner' => $feedbackOwner,
            'sourceFile' => $csvFile,
            'publishAt' => \Alaress_Date_Format::now()
        ];
    }
}
