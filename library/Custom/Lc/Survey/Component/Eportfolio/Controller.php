<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Lc\Survey\Component\Eportfolio;

use Component_Eportfolio_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\User;
use Scrub;
use SitePage;

/**
 */
class Controller extends Component_Eportfolio_ControllerAbstract
{
    /** @var SurveyMapper $surveyMapper */
    protected $surveyMapper;

    /** @var Survey $survey */
    protected $survey;

    /** @var User $sessionUser */
    protected $sessionUser;

    /** @var array $config */
    protected $config;

    /**
     * {@inheritDoc}
     *
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        // Rather annoyingly at this point the getUser() function returns an unhydrated object, so we're going to
        // read the user straight out of the session variable rather than doing a double hydration

        $this->sessionUser = $_SESSION["user"];

        parent::__construct($instance, $user, $sitepage);
    }

    /**
     * {@inheritDoc}
     *
     * Override for `Component_ControllerAbstract::parseConfig()` function to cater for this components
     * stupid visibility shenanigans.
     *
     * @param string $targetHandle
     * @param bool $is_for_template
     * @return bool
     */
    public function parseConfig($targetHandle = 'CONFIGURATION', $is_for_template = false)
    {
        // Determine what to display

        $this->setupConfigRendering($targetHandle);

        // Setup component for all non-staff roles

        if (!$this->sessionUser->getRole()->isStaff()) {
            if ($this->getConfig('collapse') !== '1') {
                // 'Display Component Heading On Student View' is ticked, remove the title bar entirely
                $this->getSitePage()->setVar('configTpl', '');
            } else {
                // Display the title bar, but remove all of the config elements
                $this->renderConfigElements(false, $is_for_template);
                $this->getSitePage()->parse($targetHandle, 'configTpl', false);
            }

            return false;
        }

        // Setup component for staff

        $this->renderConfigElements(true, $is_for_template);
        $this->getSitePage()->parse('settings_link_block', 'settings_link');
        $this->getSitePage()->parse('settings_close_block', 'settings_close');
        $this->getSitePage()->parse($targetHandle, 'configTpl', false);

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function execute()
    {
        // Setup all of the other properties required for this show to get on the road

        $this->config       = $this->getConfig();
        $this->surveyMapper = SurveyMapper::getInstance();

        // Try to load the survey we are going to render

        try {
            $this->survey = $this->surveyMapper->findById($this->config['survey']);
        } catch (MapperException $e) {
            // The survey was not found (or no survey was set in the configuration)
            if (!$this->sessionUser->getRole()->isStaff()) {
                // The logged user is not a staff member, they can't do anything with the component, so dont render it!
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $answersRenderArray = [];

        // Retrieve all responses to the survey and mash them into an array format that we can iterate and render easily

        $maximumQuestions = 0;

        $maximumQuestionsPerSection = (int)$this->config['size'];
        $showAllQuestions           = $maximumQuestionsPerSection === 0;

        if ($this->survey instanceof Survey) {
            $responses = $this->surveyMapper->getMatrix(
                $this->survey->getId(),
                $this->getContext()->getUserId()
            );

            foreach ($responses as $response) {
                // Strip the section title of tags and create an array entry for it if none already exists
                $sectionTitle = Scrub::html2text($response['section_title']);

                if (!isset($answersRenderArray[$sectionTitle])) {
                    $answersRenderArray[$sectionTitle] = [];
                }

                // Unserialize the available answers (into an array of objects) and then mash into an indexed array so
                // we can easily lookup the persons selected answer

                $answers = unserialize($response['answers']);

                $answersArray = [];

                if (is_array($answers)) {
                    foreach ($answers as $answer) {
                        $answersArray[$answer->value] = $answer->description;
                    }

                    // Add each question for the section to the array

                    // The number of questions is restricted by the components configuration

                    if ($showAllQuestions || count($answersRenderArray[$sectionTitle]) < $maximumQuestionsPerSection) {
                        // Unserialize the persons selected answer(s) and store them with the question for rendering
                        $selectedAnswers = unserialize($response['selectedAnswers']);

                        $answers = [];

                        if (is_array($selectedAnswers)) {
                            foreach ($selectedAnswers as $answer) {
                                $answers[] = $answersArray[Scrub::html2text($answer)];
                            }
                        }

                        $answersRenderArray[$sectionTitle][Scrub::html2text($response['question'])] = $answers;
                    }

                    if (count($answersRenderArray[$sectionTitle]) > $maximumQuestions) {
                        $maximumQuestions = count($answersRenderArray[$sectionTitle]);
                    }
                }
            }
        }

        // Render this mess out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@surveyEportfolio/component.twig',
            [
                'eportfolioUser'      => $this->getContext()->getUser(),
                'componentInstanceId' => $this->getInstance()->getId(),
                'componentClass'      => $this->getComponentName(),
                'config'              => $this->config,
                'survey'              => $this->survey,
                'answers'             => $answersRenderArray,
                'questionCount'       => $maximumQuestions,
                'columnWidth'         => $maximumQuestions > 0 ? (90 / $maximumQuestions) . "%" : ""
            ]
        );
    }
}
