<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\UserMapper;
use \Twig_Loader_Chain;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningFeedback';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // rules
        $canGiveFeedback = $this->getAuthRule('isLoggedIn');

        // preloads
        $preloadParticipant = function ($params) {
            return [
                'participant' => $this->preloadParticipant($params['assessmentId'], $params['studentId'])
            ];
        };

        $preloadFeedback = function ($params) {
            return [
                'feedback' => $this->preloadFeedback($params['assessmentId'], $params['feedbackId'])
            ];
        };

        $preloadParticipantAndResponse = function ($params) {
            return [
                'participant' => $this->preloadParticipant($params['assessmentId'], $params['studentId']),
                'response' => $this->preloadResponse($params['assessmentId'], $params['responseId'])
            ];
        };

        $assessmentRoute  = 'assessments/{assessmentId:\d+}';
        $participantRoute = $assessmentRoute . '/{studentId:\d+}';
        $feedbackRoute    = 'feedback/{feedbackId:\d+}';

        $this->register('POST',   "/$participantRoute/feedback",              'postParticipantFeedback', $canGiveFeedback, $preloadParticipant);
        $this->register('POST',   "/$participantRoute{/responseId}/feedback", 'postResponseFeedback', $canGiveFeedback, $preloadParticipantAndResponse);
        $this->register('DELETE', "/$assessmentRoute/$feedbackRoute",         'deleteAssessmentFeedback', $canGiveFeedback, $preloadFeedback);
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        $injector->params[Controller::class] = [
            'feedbackDataSource' => $injector->lazyGet('feedbackDataSource')
        ];
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @param $assessmentId
     * @param $studentId
     * @return Participant
     */
    protected function preloadParticipant($assessmentId, $studentId)
    {
        $assessment = $this->getAssessmentDataSource()->findById($assessmentId);
        $student    = $this->getUserDataSource()->findById($studentId);

        $this->getParticipantDataSource()->loadParticipationIntoAssessment($assessment, $student);

        return $assessment->getParticipant($student);
    }

    /**
     * @param $assessmentId
     * @param int $responseId
     * @return Assessment\IResponse
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function preloadResponse($assessmentId, $responseId)
    {
        $responses = $this->getResponseDataSource()->findForAssessment(
            $this->getAssessmentDataSource()->findById($assessmentId)
        );

        // TODO migrate this to DataSource
        foreach ($responses as $r) {
            if ($r->getId() == $responseId) {
                return $r;
            }
        }

        throw new \Schoolbox\Mapper\Exception("Response '$responseId' not found for assessment '$assessmentId'.");
    }

    /**
     * @param int $assessmentId
     * @param int $feedbackId
     * @return Assessment\IFeedback
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function preloadFeedback($assessmentId, $feedbackId)
    {
        $feedbacks = $this->getFeedbackDataSource()->findForAssessment(
            $this->getAssessmentDataSource()->findById($assessmentId)
        );

        // TODO migrate this to DataSource
        foreach ($feedbacks as $f) {
            if ($f->getId() == $feedbackId) {
                return $f;
            }
        }

        throw new \Schoolbox\Mapper\Exception("Feedback '$feedbackId' not found for assessment '$assessmentId'.");
    }

    /**
     * @return UserMapper
     */
    public function getUserDataSource()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Assessment\DataModel\MapperProxy
     */
    public function getAssessmentDataSource()
    {
        return $this->getInjector()->get('assessmentDataSource');
    }

    /**
     * @return Participant\DataSource
     */
    public function getParticipantDataSource()
    {
        return $this->getInjector()->get('participantDataSource');
    }

    /**
     * @return Assessment\Response\DataSource
     */
    public function getResponseDataSource()
    {
        return $this->getInjector()->get('responseDataSource');
    }

    /**
     * @return Assessment\Feedback\DataSource
     */
    public function getFeedbackDataSource()
    {
        return $this->getInjector()->get('feedbackDataSource');
    }
}
