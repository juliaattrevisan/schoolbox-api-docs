<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use OutOfBoundsException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\IMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Feedback;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseMapperProxy;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Authorizer\Rule\CanViewAssessable;
use Schoolbox\Learning\Authorizer\Rule\ParentCanViewFeedback;
use Schoolbox\Learning\Authorizer\Rule\StudentCanViewFeedback;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningDueWorkResponse';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException if any sub-rule is not a IAuthorizationRule
     * @throws Unauthorized If auth rule fails
     * @throws \InvalidArgumentException If mapper class not found
     * @throws MapperException\Deleted If a deleted assessment is requested
     * @throws \UnexpectedValueException If the kami files owner is not a participant
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface If unable to find service in DI
     * @throws NotFound If the requested Kami Doc has not finished processing
     * @throws OutOfBoundsException
     */
    protected function registerRoutes()
    {
        $preloadParticipant     = function (array $params) {
            return $this->preloadParticipant($params);
        };
        $preloadTurnitinWithAss = function (array $params) {
            return $this->preloadTurnitinWithAssessment($params);
        };
        $preloadPlagscanWithAss = function (array $params) {
            return $this->preloadPlagscanWithAssessment($params);
        };
        $preloadKami            = function (array $params) {
            return $this->preloadKami($params);
        };

        $hasWriteAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            $this->buildRuleHasWriteAccess()
        );

        $hasKamiAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            $this->buildRuleCanAccessKami(),
            new CanViewAssessable(new Visibility(Access::getInstance()))
        );

        $hasSimilarityAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                $this->buildRuleHasWriteAccess(),
                new Rule\All(
                    new Rule\IsUser('participant'),
                    new Rule\IsConfigOn('similarity_check_allow_students_report_access', $this->getConfigLoader())
                )
            ),
            new CanViewAssessable(new Visibility(Access::getInstance()))
        );

        $turnitinPattern    = '/similarity/{assessmentId:\d+}/{responseId:\d+}/{turnitinSubId:([a-zA-Z0-9-]+)}';
        $plagscanPattern    = '/plagscans/{assessmentId:\d+}/{responseId:\d+}/{plagscanId:\d+}';
        $assessmentPattern  = '/assessments/{assessmentId:\d+}';
        $participantPattern = $assessmentPattern . '/{studentId:\d+}';
        $responsePattern    = $assessmentPattern . '/responses/{responseId:\d+}';

        $this->register('DELETE', $responsePattern, 'deleteParticularResponse', $hasWriteAccess, $preloadParticipant); // a || r

        $this->register('GET', $participantPattern . '/responses/blind{/responseId:\d+}', 'getParticularResponseBlind', $hasWriteAccess, $preloadParticipant); // a || r
        $this->register('GET', $participantPattern . '/responses{/responseId:\d+}', 'getParticularResponse', $hasWriteAccess, $preloadParticipant); // a || r

        $this->register('GET', $turnitinPattern, 'getTurnitinReport', $hasSimilarityAccess, $preloadTurnitinWithAss); // a || r
        $this->register('GET', $turnitinPattern . '/iframe', 'getTurnitinReportContents', $hasSimilarityAccess, $preloadTurnitinWithAss); // a || r

        $this->register('GET', $plagscanPattern, 'getPlagscanReport', $hasSimilarityAccess, $preloadPlagscanWithAss); // a || r
        $this->register('GET', $plagscanPattern . '/iframe', 'getPlagscanReportContents', $hasSimilarityAccess, $preloadPlagscanWithAss); // a || r

        // For now, same auth rules as crocodoc.
        $canAccessKami = new Rule\All(
            $hasKamiAccess,
            new Kami\Authentication\IsEnabled($this->getKamiConnector())
        );
        // Don't attempt to export a kamiDoc if not possible
        $canExportKami = new Rule\All(
            $hasKamiAccess,
            new Kami\Authentication\IsEnabled($this->getKamiConnector()),
            new Rule\Callback(function (array $params) {
                $kami = $this->getKamiMapper()->findById($params['kamiId']);
                if ($kami->isStatusProcessing() || $kami->isStatusDone()) {
                    return true;
                }
                throw new Unauthorized();
            })
        );
        $this->register('GET', '/kami/{kamiId:\d+}', 'getKamiView', $canAccessKami, $preloadKami);
        $this->register('GET', '/kami/{kamiId:\d+}' . '/export', 'getKamiExport', $canExportKami, $preloadKami);
    }


    /**
     * @return object|Kami\IConnector
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    private function getKamiConnector()
    {
        return $this->injector->get('kamiConnector');
    }


    /**
     * Builds an auth rule determining if current user has WRITE+ perm to assessment's folder
     *
     * @return Rule\HasHomepageAccess
     * @throws Unauthorized If current user lacks write access to assessment folder
     */
    private function buildRuleHasWriteAccess()
    {
        return new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            function ($params) {
                if (isset($params['response']) && $params['response'] instanceof IResponse) {
                    return $params['response']->getAssessable()->getContext()->getFolder()->getId();
                }
                if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
                    return $params['assessment']->getContext()->getFolder()->getId();
                }
                throw new Unauthorized();
            },
            Access::getInstance()
        );
    }


    /**
     * Returns an auth rule determining if current user can view/download an
     * annotated student submission file (aka 'kami').
     *
     * Permitted if user is either of:
     * * SuperUser,
     * * Have WRITE+ perm to assessment's folder
     * * Student who submitted the file and students can view feedback
     * * Parent of student who submitted file, and parents can view feedback
     *
     * @return Rule\IAuthorizationRule
     * @throws \InvalidArgumentException if any sub-rule is not a IAuthorizationRule
     * @throws Unauthorized If current user not permitted to access the kami
     */
    private function buildRuleCanAccessKami()
    {
        // And combine - ensuring parent check is last
        return new Rule\Any(
            new Rule\IsSuperuser(),
            $this->buildRuleHasWriteAccess(),
            //Student who submitted the feedback and and their parents can view feedback, so long as the feedback is not a self-assessment
            new Rule\All(
                new Rule\Any(
                    new ParentCanViewFeedback(),
                    new StudentCanViewFeedback(),
                ),
                new Rule\Callback(
                    static function (array $params = []) {
                        if (isset($params['feedback']) && $params['feedback'] instanceof Feedback\Model) {
                            $feedback = $params['feedback'];
                            if ($feedback->getReviewRoute() === ReturnedWork::REVIEW_ROUTE_INSTRUCTOR) {
                                return;
                            }
                        }
                        throw new Unauthorized();
                    }
                )
            )
        );
    }


    /**
     * @param array $params
     * @return array [ assessment, participant, response ]
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    private function preloadParticipant(array $params)
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        $response = null;
        $student  = null;

        /* If a responseId was provided, load that particular response up
         */
        if ($params['responseId']) {
            // Response will already be loaded in $assessment; no need to load again.
            // As a bonus $response also contains feedbacks.
            // We don't know the participant yet though, so find that.
            foreach ($assessment->getAllParticipants() as $p) {
                foreach ($p->getAllResponses() as $r) {
                    if ($r->getId() == $params['responseId']) {
                        $response = $r;
                        $student  = $p->getUser();
                        break 2;
                    }
                }
            }
            if (!isset($student, $response)) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
            }
        } elseif ($params['studentId']) {
            /* Otherwise spawn a floating feedback instead
             */
            try {
                $response = null;
                $student  = \Schoolbox\UserMapper::getInstance()->findById($params['studentId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
            }
        }

        return [
            'assessment'  => $assessment,
            'response'    => $response,
            'student'     => $student,
        ];
    }


    /**
     * @param array $params
     * @return array [ assessment, response, turnitin ]
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    private function preloadTurnitinWithAssessment(array $params): array
    {
        try {
            $turnitinReport = Turnitin\File\Mapper::getInstance()->findOneByColumn('turnitin_submission_id', $params['turnitinSubId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when requested Assessment doesn't exist
         */
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when requested Response doesn't exist in $assessment
         */
        try {
            /** @var \Schoolbox\Learning\Assessment\DueWork\Response\Model $response */
            $response = $this->getResponseMapperProxy()->findInAssessment($params['responseId'], $assessment);
        } catch (OutOfBoundsException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /**
         * die early when the requested Turnitin Submission Id does not match any of the response files Turnitin Submission Id
         */
        //start off with the lowest perm state
        $validFile = false;
        foreach ($response->getAllFiles() as $file) {
            //check if file is a turnitin file then check if it matches the report turnitin id
            //if they do match (could be multiple files uploaded, so match on any) allow the user through
            if (null !== $file->getTurnitIn() && ($file->getTurnitIn()->getTurnitinSubmissionId() === $turnitinReport->getTurnitinSubmissionId())) {
                $validFile = true;
            }
        }
        //you are not supposed to be accessing this link. go away
        if (!$validFile) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when participant isn't found
         */
        try {
            $participant = $assessment->getParticipant($response->getSubmittedOnBehalfOf());
        } catch (OutOfBoundsException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Historical Turnitin documents don't have any reports generated for them
         * There's no link to that; just exit
         */
        if ($turnitinReport->getHistorical()) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment,
            'turnitin'  => $turnitinReport,
            'response' => $response,
            'participant' => $participant
        ];
    }


    /**
     * @param array $params
     * @return array [ assessment, response, plagscan ]
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    private function preloadPlagscanWithAssessment(array $params)
    {
        try {
            $plagscanDocument = PlagscanMapper::getInstance()->findOneByColumn('plagscan_id', $params['plagscanId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when requested Assessment doesn't exist
         */
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when requested Response doesn't exist in $assessment
         */
        try {
            $response = $this->getResponseMapperProxy()->findInAssessment($params['responseId'], $assessment);
        } catch (OutOfBoundsException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when participant isn't found
         */
        try {
            $participant = $assessment->getParticipant($response->getSubmittedOnBehalfOf());
        } catch (OutOfBoundsException $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment,
            'plagscan'  => $plagscanDocument,
            'response' => $response,
            'participant' => $participant
        ];
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $bakery = new BreadcrumbFactory(
            $this->getRouter(),
            $this->getAuthorizer(),
            $this->getMapperFactory()->get(Access::class),
        );
        return new Controller(
            $this->getMapperFactory()->get(Error\Mapper::class),
            $this->injector->get('responseDataSource'),
            $bakery,
            $this->getMapperFactory()->get(Term\Mapper::class),
            $this->getMapperFactory()->get(Kami\Mapper::class),
            $this->injector->get('kamiConnector'),
            $this->getMapperFactory()->get(Access::class),
            $this->injector->get('filesystem.storageProxy'),
        );
    }


    /**
     * Intentionally does not check the status of the Kami upload. The controller handles that.
     *
     * @param array $params
     * @return array
     * @throws MapperException\Deleted If the assessment the kami belongs to is deleted
     * @throws OutOfBoundsException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException If mapper class not found
     * @throws \UnexpectedValueException If the kami files owner is not a participant
     */
    private function preloadKami(array $params): array
    {
        $kami           = $this->getKamiMapper()->findById($params['kamiId']);
        $submissionFile = $kami->getStudentSubmissionFile();
        $submission     = $this->getSubmissionMapper()->findById($submissionFile->getSubmission()->getId());
        $assessment     = $this->getAssessmentMapperProxy()->findByDueWorkId($submission->getSubmissionBox()->getId());
        $participant    = $assessment->getParticipant($submission->getOwner());
        $response       = $this->getResponseMapperProxy()->findInAssessment($submission->getId(), $assessment);

        // fill response feedback, as it's not hydrated
        foreach ($participant->getAllFeedbacks() as $feedback) {
            // Feedback doesn't have response when given from status page
            if (
                ($feedback->getResponse() === null && $feedback->isPublished())
                || ($feedback->getResponse() && ($feedback->getResponse()->getID() === $response->getId()))
            ) {
                $response->getAllFeedback()->add($feedback);
            }
        }

        return [
            'kami'        => $kami,
            'assessment'  => $assessment,
            'student'     => $participant->getUser(),
            'response'    => $response,
            'feedback'    => $response->findMostRecentInstructorGivenPublishedFeedback(),
        ];
    }

    /* Getters / Setters
     */

    /**
     * @return Error\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }


    /**
     * @return Kami\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getKamiMapper()
    {
        return $this->getMapperFactory()->get(Kami\Mapper::class);
    }


    /**
     * @return SubmissionMapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getSubmissionMapper()
    {
        return $this->getMapperFactory()->get(SubmissionMapper::class);
    }


    /**
     * @return IConfigLoader
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function getConfigLoader()
    {
        return $this->injector->get('config');
    }


    /**
     * @return AssessmentMapperProxy
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return ResponseMapperProxy
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function getResponseMapperProxy()
    {
        return $this->injector->get('responseDataSource');
    }


    /**
     * @return FeedbackMapperProxy
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function getFeedbackMapperProxy()
    {
        return $this->injector->get('feedbackDataSource');
    }
}
