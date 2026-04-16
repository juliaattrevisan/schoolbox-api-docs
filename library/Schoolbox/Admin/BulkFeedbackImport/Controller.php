<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use League\Flysystem\FilesystemException;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\L10n\RosettaStone;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Number of minutes this source csv should exist
     */
    private const int CSV_DURATION = 15;


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
        private SubmissionBoxMapper $submissionBoxMapper,
        private ReturnedWorkMapper $returnedWorkMapper,
        private \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper $reportingMapper,
        private StateMapper $stateMapper,
        private IFeedbackDataSource $feedbackDataSource,
        protected RosettaStone $rosettaStone,
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
            /* Expire 15 minutes from now, to minimise the existence in publicly-available storage
             */
            ->setExpiryDate(\Alaress_Date_Format::now()->addMinutes(self::CSV_DURATION));

        $this->response = $this->responseFromTemplate(
            '@adminBulkFeedbackImport/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkFeedbackImport.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkFeedbackImport.getIndex')),
                /* Not sure if there's a shortcut to this: never seen one?
                 */
                'roletypeStaff' => \Schoolbox\Role::STAFF,
                'worktypes' => $this->worktypeMapper->findAll()->sortNaturally('sequence'),
                'sourceFileZone' => $zone->getTwigContext(),
            ],
            'Bulk Feedback Import'
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
        if (!\is_array($post['attachment'] ?? null)) {
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
            $this->submissionBoxMapper,
            $this->returnedWorkMapper,
            $this->reportingMapper,
            $this->stateMapper,
            $this->feedbackDataSource,
            $this->rosettaStone,
            trim((string)$this->getConfigLoader()->get('date_local')),
            $settings['assessmentTitle'],
            $settings['worktype'],
            false,
        ))->parseFeedbacks($settings['sourceFile'], $settings['feedbackAuthor']);

        $this->response = $this->responseFromTemplate(
            '@adminBulkFeedbackImport/preview.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkFeedbackImport.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkFeedbackImport.getIndex')),
                    [['Preview', null]]
                ),
                'settings' => $settings,
                'outcome' => $outcome
            ],
            'Bulk Feedback Import Preview',
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
            $this->submissionBoxMapper,
            $this->returnedWorkMapper,
            $this->reportingMapper,
            $this->stateMapper,
            $this->feedbackDataSource,
            $this->rosettaStone,
            trim((string)$this->getConfigLoader()->get('date_local')),
            $settings['assessmentTitle'],
            $settings['worktype'],
            true,
        ))->parseFeedbacks($settings['sourceFile'], $settings['feedbackAuthor']);

        $this->response = $this->responseFromTemplate(
            '@adminBulkFeedbackImport/perform.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkFeedbackImport.getIndex'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkFeedbackImport.getIndex')),
                    [['Results', null]]
                ),
                'settings' => $settings,
                'outcome' => $outcome,
            ],
            'Bulk Feedback Import Result',
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
        /* Assessment Title should maybe exist
         */
        $assessmentTitle = trim((string)($postVar['assessmentTitle'] ?? ''));

        if ('' === $assessmentTitle) {
            throw new \DomainException('Assessment title was omitted.');
        }

        /* Worktype chosen should maybe exist
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
            'assessmentTitle' => $assessmentTitle,
            'worktype' => $worktype,
            'feedbackAuthor' => $feedbackAuthor,
            'publishAt' => \Alaress_Date_Format::now()
        ];
    }
}
