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

        $this->register('POST', '/assessments/{assessmentId:\d+}/{studentId:\d+}/feedback', 'postParticipantFeedback', $canGiveFeedback, $preloadParticipant);
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
        $injector->params[Controller::class] = [];
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
        return $this->getParticipantDataSource()->findForAssessmentAndUser(
            $this->getAssessmentDataSource()->findById($assessmentId),
            $this->getUserDataSource()->findById($studentId)
        );
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
}
