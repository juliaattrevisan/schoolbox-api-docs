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
use Schoolbox\Mapper\Exception\Deleted;
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
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
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
                /* Not sure if there's a shortcut to this: never seen one?
                 */
                'roletypeStaff' => \Schoolbox\Role::STAFF,
                'worktypes' => $this->worktypeMapper->findAll()->sortNaturally('sequence'),
                'sourceFileZone' => $zone->getTwigContext(),
            ],
            'Bulk Assessment Result'
        );
    }


    /**
     * Process in planning mode
     *
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws FilesystemException
     * @throws RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postPreview(): void
    {
        $post = $this->requestPost->getArrayCopy();

        $settings = $this->verifySettings($post);

        /* Augment with a storage file
         */
        if (!\is_array($post['attachment'])) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $settings['sourceFile'] = (new CollectorFactory())->build($post['attachment'])->getFirst();

        /* This can be ported to the ui version with null and true in place of logger, perform and lock.
         */
        $outcome = (new Processor(
            $this->assessmentMapperProxy,
            $this->filesystemProxy,
            $this->userMapper,
            $this->folderMapper,
            $settings['worktype']->getId(),
            $settings['publishAt'],
            null,
            false,
        ))->parseFeedbacks($settings['sourceFile'], $settings['feedbackAuthor']);

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
     * @throws Deleted
     * @throws Error
     * @throws FilesystemException
     * @throws RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postPerform(): void
    {
        $post = $this->requestPost->getArrayCopy();

        $settings = $this->verifySettings($post);

        /* Augment with a storage file
         */
        $settings['sourceFile'] = $this->storageMapper->findByHash($post['attachment']);

        /* This can be ported to the ui version with null and true in place of logger, perform and lock.
         */
        $outcome = (new Processor(
            $this->assessmentMapperProxy,
            $this->filesystemProxy,
            $this->userMapper,
            $this->folderMapper,
            $settings['worktype']->getId(),
            $settings['publishAt'],
            null,
            true,
        ))->parseFeedbacks($settings['sourceFile'], $settings['feedbackAuthor']);

        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/perform.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkAssessmentResult.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkAssessmentResult.getIndex')),
                'settings' => $settings,
                'outcome' => $outcome,
            ],
            'Bulk Assessment Result',
        );
    }


    /**
     * Asserts that the feedback author actually exists:
     * without those, we can't really continue
     *
     * Also assumes a publish datetime here, though you probably want this to be configurable
     * at some point in the future
     *
     * @param array $postVar
     * @return array
     */
    private function verifySettings(array $postVar): array
    {
        /* Worktype chosen must exist
         */
        $worktypeId = $postVar['worktypeId'] ?? null;

        try {
            $worktype = $this->worktypeMapper->findById($worktypeId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop?
            // throw new \DomainException('Unknown work type selected, or was omitted.');
            $worktype = null;
        }

        /* Feedback Author needs to exist
         */
        $feedbackAuthorId = $postVar['userId'] ?? null;

        try {
            $feedbackAuthor = $this->userMapper->findById($feedbackAuthorId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException('Unknown feedback author selected, or was omitted.');
        }

        return [
            'worktype' => $worktype,
            'feedbackAuthor' => $feedbackAuthor,
            'publishAt' => \Alaress_Date_Format::now()
        ];
    }
}
