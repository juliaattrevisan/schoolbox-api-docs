<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkAssessmentResult;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\SummativeReport\Payload\VictorianProgression\Attendance\Processor;
use Schoolbox\Resource\Attachment\Zone;

class Controller extends Base
{
    /**
     * Constructor.
     */
    public function __construct(
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
     */
    public function postPreview(): void
    {
        $settings = $this->verifySettings($this->requestPost->getArrayCopy());

//        /* This can be ported to the ui version with null and true in place of logger, perform and lock.
//         */
//        (new Processor(
//            $this->userMapper,
//            $this->folderMapper,
//            null,
//            $settings['worktype']->getId(),
//            \Alaress_Date_Format::now()->getUsingFormat('d/m/Y H:ia'),
//            null,
//            null,
//            true,
//        ))->parseFeedbacks($storageFile, $settings['feedbackOwner'], null);

        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/preview.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkAssessmentResult.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkAssessmentResult.getIndex')),
                'settings' => $settings,
            ],
            'Bulk Assessment Result Preview',
        );
    }


    /**
     * Process in perform mode
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
     * Asserts that the selected worktype and feedback owner actually exists:
     * without those, we can't really continue
     *
     * @param array $postVar
     * @return array
     */
    private function verifySettings(array $postVar): array
    {
        $worktypeId = $postVar['worktypeId'] ?? null;
        try {
            $worktype = $this->worktypeMapper->findById($worktypeId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException('Unknown work type selected, or was omitted.');
        }

        $feedbackOwnerId = $postVar['userId'] ?? null;

        try {
            $feedbackOwner = $this->userMapper->findById($feedbackOwnerId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \DomainException('Unknown feedback owner selected, or was omitted.');
        }

        return [
            'worktype' => $worktype,
            'feedbackOwner' => $feedbackOwner,
        ];
    }
}
