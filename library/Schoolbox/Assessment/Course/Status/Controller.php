<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Status;

use Alaress_Date_Format;
use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\InlineGrader\Kami\Connector;
use Alaress\Web\Controller as WebController;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class Controller extends WebController\Base
{
    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param AssessmentMapperProxy $assessmentDataSource
     * @param ResponseDataSource $responseDataSource
     * @param ReturnedWorkMapper $returnedWorkMapper
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     * @param Connector $kamiConnector
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private AssessmentDataSource $assessmentDataSource,
        private ResponseDataSource $responseDataSource,
        private ReturnedWorkMapper $returnedWorkMapper,
        private Project\ResultCache\Mapper $projectResultCacheMapper,
        private Connector $kamiConnector,
    ) {
    }

    /**
     * publish feedback for all assessments imported from a course level assessment
     *
     * @param Model $courseSpec
     * @param IAssessable $assessmentSpec
     * @return void
     * @throws Exception
     * @throws Deleted
     * @api POST /learning/courseMarkbook/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/publish
     */
    public function postPublishFeedback(CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        /** @var int[] $draftFeedbackIds */
        $draftFeedbackIds = $this->requestPost->get('drafts', []);
        $now              = Alaress_Date_Format::now();
        $results = [];
        $projectCache = (new Project\ResultCache\ResultCache($this->projectResultCacheMapper));

        // Fetch assessments
        $assessments = $this->assessmentMapperProxy->findAllImplementationsBySpecificationId($assessmentSpec->getId())->indexBy('id');

        // hydrate participants
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        foreach ($draftFeedbackIds as $draftFeedbackId) {
            // Verify that this return is for this assessment
            $submissionReturn = $this->returnedWorkMapper->findById($draftFeedbackId);
            $publishSuccess = false;

            // grab the actual assessment
            /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessment */
            $assessment = $assessments->getBy('id', $submissionReturn->getSubmissionBox()->getAssessmentModel()->getId());

            if (!$assessment) {
                $results[$draftFeedbackId] = ['publishSuccess' => false, 'error' => 'Assessment not found'];
                continue;
            }

            $submissionReturn->setPublishedAt($now);

            try {
                $this->returnedWorkMapper->save($submissionReturn);
                $publishSuccess = true;

                // Find the Kami worksheet (if it exists)
                if ($submissionReturn->getSubmission()) {
                    $responses = $this->responseDataSource->findForAssessment($assessment, $submissionReturn->getSubmission()->getOwner());
                    if (0 !== $responses->count()) {
                        $response = null;
                        foreach ($responses as $responseCurrent) {
                            if ($responseCurrent->getId() == $submissionReturn->getSubmission()->getId()) {
                                $response = $responseCurrent;
                                break;
                            }
                        }

                        // attempt to clean as we go
                        unset($responses);

                        /** @var ?DueWork\Response\Model $response */
                        $kamiModel = $response?->getAllFiles()->filter(function (SubmissionFile $file) {
                            return $file->getIsWorksheet();
                        })->first()?->getKami();
                        if ($kamiModel) {
                            // Save the kami doc to the feedback object
                            $this->kamiConnector->initiatePollingForKamiDownload(
                                $kamiModel,
                                'feedback',
                                $this->getUser(),
                            );
                        }
                    }
                }

                /* Update project result cache when Publish All is used
                 */
                $projectCache->refresh($this->assessmentDataSource, $assessment, $submissionReturn->getOwner());
            } catch (PDOException | \DomainException | \InvalidArgumentException | \LogicException | \UnexpectedValueException $e) {
                $publishSuccess = false;
            }

            $results[$draftFeedbackId] = ['publishSuccess' => $publishSuccess];
        }

        $this->response = $this->responseAsJson($results);
    }
}
