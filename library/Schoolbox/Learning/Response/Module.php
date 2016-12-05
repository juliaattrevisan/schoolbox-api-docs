<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Response;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use \Schoolbox\Learning\Assessment\Response\DataSource as ResponseMapperProxy;
use \Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackMapperProxy;
use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;

use \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SingleFile;
use \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SingleFileMapper;
use \Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission as Submission;
use \Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as SubmissionMapper;
use \Schoolbox\Learning\Assessment\IAssessable;

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
        $this->register('DELETE', '/assessments/{assessmentId}/responses/{responseId}', 'deleteParticularResponse');
    }

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [

                /* Download an annotated crocodoc
                 */
                '/responses/{assessmentId}/{responseId}/{feedbackId}/download/{crocodocId}'  => 'getCrocodocDownload',

                /* View an annotated crocodoc
                 */
                '/responses/{assessmentId}/{responseId}/{feedbackId}/annotated/{crocodocId}' => 'getCrocodocView',

                /* View a Response File's plagscan report iframe
                 * View a Response File's plagscan report contents
                 */
                '/responses/plagscan/{plagscanId}'                                           => 'getPlagscanReport',
                '/responses/plagscan/{plagscanId}/contents'                                  => 'getPlagscanReportContents',

                /* View a Response in the Inline Grader
                 */
                '/responses/{assessmentId}/{studentId}{/responseId}'                         => 'getParticularResponse',

            ],
            'POST' => []
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(getParticularResponse|getPlagscanReport|getPlagscanReportContents)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            ));

        $ruleRegexp = '(?:getCrocodocDownload)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\HasRole(Role::STUDENT)
            ));
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '(get|delete)ParticularResponse', function ($params) {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);

            $participant = null;
            $response = null;

            /* If a responseId was provided, load that particular response up
             */
            if ($params['responseId']) {
                try {
                    $response = $this->getResponseMapperProxy()->findInAssessment($params['responseId'], $assessment);
                    $participant = $assessment->getParticipant($response->getSubmittedOnBehalfOf());
                } catch (\OutOfBoundsException $e) {
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
        });

        $this->addRoutePreload($matcher, 'getCrocodoc(View|Download)', function ($params) {

            /* Die early when requested Assessment doesn't exist
             */
            try {
                $assessment = $this->getAssessmentMapperProxy()->findById($params['assessmentId']);
            }
            catch (\Schoolbox\Mapper\Exception $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            /* Die early when requested Response doesn't exist in $assessment
             */
            try {
                $response = $this->getResponseMapperProxy()->findInAssessment($params['responseId'], $assessment);
            }
            catch (\OutOfBoundsException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            /* Get all the feedbacks associated with $assessment, then find the requested Feedback
             */
            $feedbacks = $this->getFeedbackMapperProxy()->findForAssessment($assessment);

            /* Need this $feedback to determine whether the requested
             * crocodoc annotation is part of a published feedback
             */
            $feedback = null;

            foreach ($feedbacks as $currentFeedback) {
                if ($currentFeedback->getId() == $params['feedbackId']) {
                    $feedback = $currentFeedback;
                    break;
                }
            }

            /* Die early when requested Feedback doesn't exist
             */
            if (!$feedback) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            /* Factory to figure out which fragment is required
             */
            $file = null;

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

            /* Otherwise you succeeded in running the gauntlet
             * Shove all required ingredients into Controller at last
             */
            return [
                'response' => $response,
                'feedback' => $feedback,
                'file' => $file
            ];
        });

        $this->addRoutePreload($matcher, 'getPlagscanReport', function ($params) {

            /* @var Plagscan $document */
            try {
                $plagscanDocument = PlagscanMapper::getInstance()->findOneByColumn('plagscan_id', $params['plagscanId']);
            }
            catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            /* Get a whole bunch of extra crap for the gd breadcrumbs
             *
             * And thus the sky was torn asunder and the tears of angels fell as rain,
             * hydrating the dessicated earth with a multitude of data
             */
            /** @var SingleFile $submissionFile */
            $submissionFile = SingleFileMapper::getInstance()->findById($plagscanDocument->getStudentSubmissionFileId());

            /** @var Submission $submission */
            $submission = SubmissionMapper::getInstance()->findById($submissionFile->getSubmission()->getId());

            /** @var IAssessable $assessment */
            $assessmentId = $submission->getSubmissionBox()->getAssessmentModel()->getId();
            $assessment = $this->getAssessmentMapperProxy()->findById($assessmentId);

            return [
                'plagscan' => $plagscanDocument,
                'assessment' => $assessment,
            ];
        });

        $this->addRoutePreload($matcher, 'getPlagscanReportContents', function ($params) {

            /* @var Plagscan $plagscanDocument */
            try {
                $plagscanDocument = PlagscanMapper::getInstance()->findOneByColumn('plagscan_id', $params['plagscanId']);
            }
            catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            return [
                'plagscan' => $plagscanDocument,
            ];
        });
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
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return ResponseMapperProxy
     */
    protected function getResponseMapperProxy()
    {
        return $this->injector->get('responseDataSource');
    }

    /**
     * @return FeedbackMapperProxy
     */
    protected function getFeedbackMapperProxy()
    {
        return $this->injector->get('feedbackDataSource');
    }
}
