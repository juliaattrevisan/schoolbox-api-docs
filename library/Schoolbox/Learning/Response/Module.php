<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Response;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use OutOfBoundsException;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Authorizer\Rule\IsParticipant;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DueWork\Feedback;
use Schoolbox\Learning\Assessment\DueWork\Submission;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use \Schoolbox\Learning\Assessment\Response\DataSource as ResponseMapperProxy;
use \Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackMapperProxy;
use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningResponse';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $preloadParticipant     = function (array $params) { return $this->preloadParticipant($params); };
        $preloadResponse        = function (array $params) { return $this->preloadResponseWithFeedback($params); };
        $preloadPlagscanWithAss = function (array $params) { return $this->preloadPlagscanWithAssessment($params); };

        /**
         * @param $params['response'] IResponse
         * @param $params['assessment'] IAssessable
         */
        $hasWriteAccess = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            function ($params) {
                if ($params['response'] instanceof IResponse) {
                    return $params['response']->getAssessable()->getContext()->getFolder()->getId();
                } elseif ($params['assessment'] instanceof IAssessable) {
                    return $params['assessment']->getContext()->getFolder()->getId();
                } elseif ($params['contextId']) {
                    return $params['contextId'];
                } else {
                    throw new Unauthorized();
                }
            },
            Access::getInstance()
        );

        /**
         * @param $params['response'] IResponse
         * @param $params['assessment'] IAssessable
         * @param $params['participant'] Participant
         */
        $hasWriteOrIsParticipant = new Rule\Any(
            $hasWriteAccess,
            new IsParticipant()
        );

        $hasPlagscanAccess = new Rule\Any(
            $hasWriteAccess,
            new Rule\All(
                new IsParticipant(),
                new Rule\IsConfigOn('plagscan_allow_students_report_access', $this->getConfigLoader())
            )
        );

        $plagscanPattern    = '/plagscans/{assessmentId:\d+}/{responseId:\d+}/{plagscanId:\d+}';
        $assessmentPattern  = '/assessments/{assessmentId:\d+}';
        $participantPattern = $assessmentPattern . '/{studentId:\d+}';
        $responsePattern    = $assessmentPattern . '/responses/{responseId:\d+}';
        $feedbackPattern    = $assessmentPattern . '/feedback/{feedbackId:\d+}';
        $crocodocPattern    = $feedbackPattern . '/{crocodocId:[0-9a-f-]+}';

        $this->register('DELETE', $responsePattern, 'deleteParticularResponse',  $hasWriteAccess, $preloadParticipant); // a || r

        $this->register('GET', $participantPattern . '/responses{/responseId:\d+}', 'getParticularResponse', $hasWriteAccess, $preloadParticipant); // a || r

        $this->register('GET', $crocodocPattern . '/download',  'getCrocodocDownload', $hasWriteOrIsParticipant, $preloadResponse); // a || r
        $this->register('GET', $crocodocPattern . '/annotated', 'getCrocodocView',     $hasWriteOrIsParticipant, $preloadResponse); // a || r

        $this->register('GET', $plagscanPattern,             'getPlagscanReport',         $hasPlagscanAccess, $preloadPlagscanWithAss); // a || r
        $this->register('GET', $plagscanPattern . '/{contextId:\d+}/iframe', 'getPlagscanReportContents', $hasPlagscanAccess, $preloadPlagscanWithAss); // a || r
    }


    /**
     * @param array $params
     * @return array [ assessment, participant, response ]
     */
    private function preloadParticipant(array $params)
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        }
        catch (\Schoolbox\Mapper\Exception $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        $participant = null;
        $response = null;

        /* If a responseId was provided, load that particular response up
         */
        if ($params['responseId']) {
            try {
                $response = $this->getResponseMapperProxy()->findInAssessment($params['responseId'], $assessment);
                $participant = $assessment->getParticipant($response->getSubmittedOnBehalfOf());
            } catch (OutOfBoundsException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        }
        /* Otherwise spawn a floating feedback instead
         */
        elseif ($params['studentId']) {
            try {
                $response = null;
                $student = \Schoolbox\UserMapper::getInstance()->findById($params['studentId']);
                $participant = $assessment->getParticipant($student);
            }
            catch (\Schoolbox\Mapper\Exception $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        }

        return [
            'assessment' => $assessment,
            'participant' => $participant,
            'response' => $response,
        ];
    }

    /**
     * @param array $params
     * @return array [ response, feedback, file ]
     */
    private function preloadResponseWithFeedback(array $params)
    {

        /* Die early when requested Assessment doesn't exist
         */
        try {
            $assessment = $this->getAssessmentMapperProxy()->findById($params['assessmentId']);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Get all the feedbacks associated with $assessment, then find the requested Feedback
         * Find everything the hard way
         */
        try {
            /** @var Feedback\Model $feedback */
            $feedbacks = $this->getFeedbackMapperProxy()->findForAssessment($assessment);
            $feedback = false;

            foreach ($feedbacks as $currentFeedback) {
                if ($currentFeedback->getId() == $params['feedbackId']) {
                    $feedback = $currentFeedback;
                }
            }

            if (!$feedback) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            $responses = $this->getResponseMapperProxy()->findForAssessment($assessment);
            $response = false;

            foreach ($responses as $currentResponse) {
                if ($currentResponse->getId() == $feedback->getResponse()->getId()) {
                    $response = $currentResponse;
                }
            }

            if (!$response) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
        }
        catch (OutOfBoundsException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Factory to figure out which fragment is required
         */
        $file = null;

        /** @var Submission\Model $response */
        foreach ($response->getAllFiles() as $currentFile) {
            if ($currentFile->getCrocodocId() == $params['crocodocId']) {
                $file = $currentFile;
                break;
            }
        }

        /* Die early when requested crocodocked File doesn't exist
         */
        if (!$file) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Spawn participant(s)
         */
        $this->getAssessmentMapperProxy()->loadParticipantsIntoAssessment($assessment, $feedback->getReviewee());

        /* Otherwise you succeeded in running the gauntlet
         * Shove all required ingredients into Controller at last
         */
        return [
            'response' => $response,
            'feedback' => $feedback,
            'file' => $file,
            'participant' => $assessment->getParticipant($feedback->getReviewee())
        ];
    }

    /**
     * @param array $params
     * @return array [ assessment, response, plagscan ]
     */
    private function preloadPlagscanWithAssessment(array $params)
    {
        try {
            $plagscanDocument = PlagscanMapper::getInstance()->findOneByColumn('plagscan_id', $params['plagscanId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when requested Assessment doesn't exist
         */
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when requested Response doesn't exist in $assessment
         */
        try {
            $response = $this->getResponseMapperProxy()->findInAssessment($params['responseId'], $assessment);
        } catch (OutOfBoundsException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* Die early when participant isn't found
         */
        try {
            $participant = $assessment->getParticipant($response->getSubmittedOnBehalfOf());
        } catch (OutOfBoundsException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment,
            'response' => $response,
            'plagscan' => $plagscanDocument,
            'participant' => $participant
        ];
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
        $this->addTemplatePath($templateLoader, __DIR__ . '/../MarkScheme/templates', 'learningMarkScheme');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'responseDataSource' => $injector->lazyGet('responseDataSource'),
                'bakery' => $injector->lazyNew(BreadcrumbFactory::class, [
                    'router' => $injector->lazyGet('router')
                ]),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return object|IConfigLoader
     */
    protected function getConfigLoader()
    {
        return $this->injector->get('config');
    }

    /**
     * @return Mapper|UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return object|AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return object|ResponseMapperProxy
     */
    protected function getResponseMapperProxy()
    {
        return $this->injector->get('responseDataSource');
    }

    /**
     * @return object|FeedbackMapperProxy
     */
    protected function getFeedbackMapperProxy()
    {
        return $this->injector->get('feedbackDataSource');
    }
}
