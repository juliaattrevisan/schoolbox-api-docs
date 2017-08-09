<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use OutOfBoundsException;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Authorizer\Rule\IsParticipant;
use Schoolbox\Learning\Authorizer\Rule\ParentCanViewFeedback;
use Schoolbox\Learning\Authorizer\Rule\StudentCanViewFeedback;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use \Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DueWork\Feedback;
use Schoolbox\Learning\Assessment\DueWork\Submission;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseMapperProxy;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Assessment\Term;

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
     */
    protected function registerRoutes()
    {
        $preloadParticipant     = function (array $params) { return $this->preloadParticipant($params); };
        $preloadResponse        = function (array $params) { return $this->preloadResponseWithFeedback($params); };
        $preloadPlagscanWithAss = function (array $params) { return $this->preloadPlagscanWithAssessment($params); };

        $hasWriteAccess = $this->buildRuleHasWriteAccess();

        $canAccessCrocodoc = $this->buildRuleCanAccessCrocodoc();

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

        $this->register('GET', $crocodocPattern . '/download',  'getCrocodocDownload', $canAccessCrocodoc, $preloadResponse); // a || r
        $this->register('GET', $crocodocPattern . '/annotated', 'getCrocodocView',     $canAccessCrocodoc, $preloadResponse); // a || r

        $this->register('GET', $plagscanPattern,             'getPlagscanReport',         $hasPlagscanAccess, $preloadPlagscanWithAss); // a || r
        $this->register('GET', $plagscanPattern . '/{contextId:\d+}/iframe', 'getPlagscanReportContents', $hasPlagscanAccess, $preloadPlagscanWithAss); // a || r
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
                if ($params['response'] instanceof IResponse) {
                    return $params['response']->getAssessable()->getContext()->getFolder()->getId();
                }
                if ($params['assessment'] instanceof IAssessable) {
                    return $params['assessment']->getContext()->getFolder()->getId();
                }
                if ($params['contextId']) {
                    return $params['contextId'];
                }
                throw new Unauthorized();
            },
            Access::getInstance()
        );
    }


    /**
     * Returns an auth rule determining if current user can view/download an
     * annotated student submission file (aka 'crocodoc').
     *
     * Permitted if user is either of:
     * * SuperUser,
     * * Have WRITE+ perm to assessment's folder
     * * Student who submitted the file and students can view feedback
     * * Parent of student who submitted file, and parents can view feedback
     *
     * @return Rule\IAuthorizationRule
     * @throws \InvalidArgumentException if any sub-rule is not a IAuthorizationRule
     * @throws Unauthorized If current user not permitted to access the crocodoc
     */
    private function buildRuleCanAccessCrocodoc()
    {
        // And combine - ensuring parent check is last
        return new Rule\Any(
            new Rule\IsSuperuser(),
            $this->buildRuleHasWriteAccess(),
            new ParentCanViewFeedback(),
            new StudentCanViewFeedback()
        );
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

            if ($feedback->getResponse() instanceof IResponse) {
                foreach ($responses as $currentResponse) {
                    if ($currentResponse->getId() == $feedback->getResponse()->getId()) {
                        $response = $currentResponse;
                    }
                }
            }

            /* No response found
             * This can happen when there really isn't a response that meets the params specified,
             * or when a previously-associated feedback became floating.
             */
            if (!$response) {

                /* If a crocodoc id exists, it may have been in a Response which had an Associated
                 * Feedback that got converted into a Floating Feedback. This was a misbehaviour that
                 * was repaired previously, but there might be some orphaned data as a result.
                 *
                 * In this case, look for a Response that contained the crocodoc id. If you can't find
                 * one, then die with GENERIC_NOT_FOUND.
                 */
                if (strlen($params['crocodocId'])) {
                    $response = $this->getResponseMapperProxy()->findByCrocodocId($assessment, $params['crocodocId']);
                }

                /* Still didn't find it? Then it really didn't exist
                 */
                if (!$response) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }
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
            // Need to cache HTML value as the cache will clear it if this model is used in a different context
            $html = $plagscanDocument->getHtml();
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
            'plagscan' => $plagscanDocument->setHtml($html), /* restore cached HTML value */
            'participant' => $participant
        ];
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
                'termMapper' => $this->getMapperFactory()->getLazy(Term\Mapper::class),
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
