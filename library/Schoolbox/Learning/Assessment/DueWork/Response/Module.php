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
use Aura\Di\ContainerInterface;
use OutOfBoundsException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Error;
use Schoolbox\IConfigLoader;
use Schoolbox\IMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Authorizer\Rule\CanSelfAssess;
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
     * @throws \Aura\Di\Exception\ServiceNotFound If unable to find service in DI
     * @throws NotFound If the requested Kami Doc has not finished processing
     * @throws OutOfBoundsException
     */
    protected function registerRoutes()
    {
        $preloadParticipant     = function (array $params) { return $this->preloadParticipant($params); };
        $preloadPlagscanWithAss = function (array $params) { return $this->preloadPlagscanWithAssessment($params); };
        $preloadKami            = function (array $params) { return $this->preloadKami($params); };

        $hasWriteAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            $this->buildRuleHasWriteAccess()
        );

        $hasKamiAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            $this->buildRuleCanAccessKami(),
            new CanViewAssessable(new Visibility(Access::getInstance()))
        );

        $hasSelfAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                $this->buildRuleHasWriteAccess(),
                new CanSelfAssess()
            ),
            new CanViewAssessable(new Visibility(Access::getInstance()))
        );

        $hasPlagscanAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                $this->buildRuleHasWriteAccess(),
                new Rule\All(
                    new Rule\IsUser('participant'),
                    new Rule\IsConfigOn('plagscan_allow_students_report_access', $this->getConfigLoader())
                )
            ),
            new CanViewAssessable(new Visibility(Access::getInstance()))
        );

        $plagscanPattern    = '/plagscans/{assessmentId:\d+}/{responseId:\d+}/{plagscanId:\d+}';
        $assessmentPattern  = '/assessments/{assessmentId:\d+}';
        $participantPattern = $assessmentPattern . '/{studentId:\d+}';
        $responsePattern    = $assessmentPattern . '/responses/{responseId:\d+}';

        $this->register('DELETE', $responsePattern, 'deleteParticularResponse',  $hasWriteAccess, $preloadParticipant); // a || r

        $this->register('GET', $participantPattern . '/responses{/responseId:\d+}', 'getParticularResponse', $hasWriteAccess, $preloadParticipant); // a || r
        $this->register('GET', $participantPattern . '/responses/self/{responseId:\d+}', 'getParticularResponseForSelfReflection', $hasSelfAccess, $preloadParticipant); // a || r

        $this->register('GET', $plagscanPattern,             'getPlagscanReport',         $hasPlagscanAccess, $preloadPlagscanWithAss); // a || r
        $this->register('GET', $plagscanPattern . '/iframe', 'getPlagscanReportContents', $hasPlagscanAccess, $preloadPlagscanWithAss); // a || r

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
        $this->register('GET', '/kami/{kamiId:\d+}' . '',        'getKamiView',   $canAccessKami, $preloadKami);
        $this->register('GET', '/kami/{kamiId:\d+}' . '/export', 'getKamiExport', $canExportKami, $preloadKami);
    }


    /**
     * @return object|Kami\IConnector
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function getKamiConnector()
    {
        return $this->getInjector()->get('kamiConnector');
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
                if ($params['response'] instanceof IResponse && $params['response']->getAssessable()) {
                    return $params['response']->getAssessable()->getContext()->getFolder()->getId();
                }
                if ($params['assessment'] instanceof IAssessable) {
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
            new ParentCanViewFeedback(),
            new StudentCanViewFeedback()
        );
    }


    /**
     * @param array $params
     * @return array [ assessment, participant, response ]
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    private function preloadParticipant(array $params)
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        }
        catch (\Schoolbox\Mapper\Exception $e) {
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
        }
        /* Otherwise spawn a floating feedback instead
         */
        elseif ($params['studentId']) {
            try {
                $response = null;
                $student  = \Schoolbox\UserMapper::getInstance()->findById($params['studentId']);
            }
            catch (\Schoolbox\Mapper\Exception $e) {
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
     * @return array [ assessment, response, plagscan ]
     * @throws \Aura\Di\Exception\ServiceNotFound
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
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'errorMapper' => $this->getMapperFactory()->getLazy(Error\Mapper::class),
                'responseDataSource' => $injector->lazyGet('responseDataSource'),
                'bakery' => $injector->lazyNew(BreadcrumbFactory::class, [
                    'router' => $injector->lazyGet('router')
                ]),
                'termMapper' => $this->getMapperFactory()->getLazy(Term\Mapper::class),
                'kamiMapper' => $this->getMapperFactory()->getLazy(Kami\Mapper::class),
                'kamiConnector' => $injector->lazyGet('kamiConnector'),
            ])
        );
    }


    /**
     * Intentionally does not check the status of the Kami upload. The controller handles that.
     *
     * @param array $params
     * @return array
     * @throws MapperException\Deleted If the assessment the kami belongs to is deleted
     * @throws OutOfBoundsException
     * @throws \Aura\Di\Exception\ServiceNotFound
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

        return [
            'kami'        => $kami,
            'assessment'  => $assessment,
            'student'     => $participant->getUser(),
            'response'    => $response,
            'feedback'    => $participant->findMostRecentInstructorGivenPublishedFeedback(),
        ];
    }

    /* Getters / Setters
     */

    /**
     * @return Error\Mapper|IMapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }


    /**
     * @return IMapper|Kami\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getKamiMapper()
    {
        return $this->getMapperFactory()->get(Kami\Mapper::class);
    }


    /**
     * @return IMapper|SubmissionMapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getSubmissionMapper()
    {
        return $this->getMapperFactory()->get(SubmissionMapper::class);
    }


    /**
     * @return object|IConfigLoader
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getConfigLoader()
    {
        return $this->injector->get('config');
    }


    /**
     * @return object|AssessmentMapperProxy
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return object|ResponseMapperProxy
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getResponseMapperProxy()
    {
        return $this->injector->get('responseDataSource');
    }


    /**
     * @return object|FeedbackMapperProxy
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getFeedbackMapperProxy()
    {
        return $this->injector->get('feedbackDataSource');
    }
}
