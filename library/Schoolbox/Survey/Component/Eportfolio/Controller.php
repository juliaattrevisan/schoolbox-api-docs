<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Component\Eportfolio;

use Component_Eportfolio_ControllerAbstract;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\User;

class Controller extends Component_Eportfolio_ControllerAbstract
{
    /** @var Survey $survey  */
    protected $survey;

    /** @var SurveyMapper $surveyMapper */
    protected $surveyMapper;

    /** @var User $currentUser */
    protected $currentUser;

    /** @var array $config */
    protected $config;

    /**
     * Work out whether we should display this thing or not
     * @return bool
     */
    public function execute()
    {
        $this->surveyMapper = SurveyMapper::getInstance();
        $this->config = $this->getConfig();
        $this->currentUser = $this->getSitePage()->getDi()->get('user');

        // Try to retrieve the survey this component is linked to

        try {
            $this->survey = $this->surveyMapper->findById($this->config['survey']);

        } catch (MapperException $e) {

            // The survey was not found, only show the component if the user is a staff member so they can configure it

            if ($this->currentUser->getRole()->isStaff || $this->currentUser->isSuperuser()) {
                return true;
            } else {
                return false;
            }
        }

        // We found the survey, lets get this party on the road

        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $answersRenderArray = [];

        // Retrieve all responses to the survey and mash them into an array format that we can iterate and render easily

        if ($this->survey instanceof Survey) {

            $responses = $this->surveyMapper->getMatrix(
                $this->survey->getId(),
                $this->getContext()->getUserId()
            );

            foreach ($responses as $response) {

                // Strip the section title of tags and create an array entry for it if none already exists

                $sectionTitle = strip_tags($response['section_title']);

                if (!isset($answersRenderArray[$sectionTitle])) {
                    $answersRenderArray[$sectionTitle] = [];
                }

                // Unserialize the available answers (into an array of objects) and then mash into an indexed array so we can easily lookup
                // the persons selected answer

                $answers = unserialize($response['answers']);

                $answersArray = [];

                foreach ($answers as $answer) {
                    $answersArray[$answer->value] = $answer->description;
                }

                // Add each question for the section to the array

                // The number of questions is restricted by the components configuration

                if (count($answersRenderArray[$sectionTitle]) < $this->config['size']) {

                    // Unserialize the persons selected answer(s) and store them with the question for rendering

                    $selectedAnswers = unserialize($response['selectedAnswers']);

                    $answers = [];

                    foreach ($selectedAnswers as $answer) {
                        $answers[] = $answersArray[$answer];
                    }

                    $answersRenderArray[$sectionTitle][strip_tags($response['question'])] = $answers;
                }
            }
        }

        // There is a bit of fudgery going on here. We need to hide the configuration options from students, but
        // students have write access to ePortfolio pages- so the normal component configuration visible setting
        // fails miserable on ePortfolio components.

        // Staff and superusers can see the heading

        $showStaffHeading = $this->currentUser->getRole()->isStaff() || $this->currentUser->isSuperuser();

        // Students see a static title bar

        $showStudentHeading = (!$this->currentUser->getRole()->isStaff() && !$this->currentUser->isSuperuser()) && $this->config['collapse'];

        // Render this mess out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@surveyEportfolio/component.twig',
            [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'config'                => $this->config,
                'showStudentHeading'    => $showStudentHeading,
                'survey'                => $this->survey,
                'answers'               => $answersRenderArray,
                'columnWidth'           => 90 / $this->config['size']
            ],
            true,                       // This does nothing useful on ePortfolio components
            $showStaffHeading,          // Show configuration from PHPLIB if the user is staff/superuser
            $showStudentHeading         // We need to raw render if showing the student heading, the heading HTML is in the Twig template
        );
    }
}
