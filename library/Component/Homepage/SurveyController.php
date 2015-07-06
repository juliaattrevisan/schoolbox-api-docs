<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_SurveyController extends Component_Homepage_ControllerAbstract
{

    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'Survey';

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {

        $this->groupHandler = new Group();

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'survey')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'survey', 'survey_block');
        $this->setBlock($templateHandle, 'createSurvey', 'createSurvey_block');
        $this->setBlock('survey', 'teacherLink', 'teacherLink_block');
        $this->setBlock('survey', 'surveyNoLink', 'surveyNoLink_block');
        $this->setBlock('survey', 'surveyLink', 'surveyLink_block');
        $this->setBlock('survey', 'resumeLink', 'resumeLink_block');
        $this->setBlock('survey', 'reviewLink', 'reviewLink_block');

        /* Initial state of various blocks
         */
        $this->setVar('resumeLink_block', '');
        $this->setVar('reviewLink_block', '');

        $surveyHandler  = new Survey_Main();
        $attemptHandler = new Survey_Attempt();
        $sectionHandler = new Survey_Section();
        $userHandler    = new User();

        $allSurveys = $surveyHandler->getList($this->getContext()->getId());

        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('createSurvey_block', 'createSurvey');
        } else {
            $this->setVar('createSurvey_block', '');
        }

        $this->setVar('survey_block', '');

        if (is_array($allSurveys)) {
            $i = 0;

            foreach ($allSurveys as $currentSurvey) {
                // Do not show the survey if you are a participant and the survey is not yet open
                if (($this->permission >= WRITE_ACCESS) || $surveyHandler->isOpen($currentSurvey)) {
                    $createdTime = new Alaress_Date_Format($currentSurvey['created_at'], 'Y-m-d H:i:s');

                    $author = $userHandler->getByID($currentSurvey['user_id']);

                    $this->setVar(
                        array(
                            'SURVEY_ASSESS_ID'      => $currentSurvey['assess_id'],
                            'SURVEY_TITLE'          => Scrub::text($currentSurvey['title']),
                            'SURVEY_START_DATE'     => date('jS M Y', strtotime($currentSurvey['start'])),
                            'SURVEY_FINISH_DATE'    => 'Due ' . date('jS M Y', strtotime($currentSurvey['finish'])),
                            'SURVEY_DESCRIPTION'    => Scrub::html($currentSurvey['description']),
                            'SURVEY_CREATED'        => $createdTime->getRelativeTime(),
                            'SURVEY_USER_ID'        => $author['uid'],
                            'SURVEY_USER_FIRSTNAME' => $author['firstname'],
                            'SURVEY_USER_LASTNAME'  => $author['lastname'],
                            'ON_OFF'                => $i++ % 2 ? 'dark' : '',
                        )
                    );

                    /* Check if you have completed an assessment already
					 *
					 * Set up Attempt vars
					 */
                    $attempt              = $attemptHandler->getLatestForUser($currentSurvey['assess_id'], $_SESSION['id']);
                    $attemptStartTime     = strtotime($attempt['start']);
                    $attemptTimeRemaining = $attemptStartTime + $currentSurvey['duration'] - time();
                    $attemptEndTime       = $attemptStartTime + $currentSurvey['duration'];

                    /* Tests can be undertaken if:
                  	 *
	                 * 1. The assessment allows resit and the assessment hasn't expired
	                 * 2. The assessment is one-time-only, and you haven't completed an attempt, and have time to do it, and have not yet attempted
	                 * 3. The current time has passed the test's starting time
	                 * 4. You aren't the owner of the test
                   	 */
                    if (is_array($attempt)) {
                        $numAttempts  = $attemptHandler->userHasAttempted($currentSurvey['assess_id'], $_SESSION['id']);
                        $firstSection = $sectionHandler->getFirst($currentSurvey['assess_id']);

                        // If the last assess is not completed provide resume link
                        if (!$attempt['finish']
                            && ($attemptTimeRemaining < 0
                                || $currentSurvey['duration'] == Assess_Main::UNLIMITED_DURATION
                            )
                        ) {
                            $this->setVar(
                                array(
                                    'SURVEY_ASSESS_ID'   => $currentSurvey['assess_id'],
                                    'SURVEY_SECTION_ID'  => $firstSection['section_id'],
                                    'SURVEY_FINISH_DATE' => 'In progress'
                                )
                            );

                            $this->parse('resumeLink_block', 'resumeLink');
                            $this->setVar('surveyLink_block', '');
                            $this->setVar('surveyNoLink_block', '');
                        } // allow to resit
                        else {
                            if (!(($currentSurvey['max_attempts'] > 0) && ($numAttempts + 1 > $currentSurvey['max_attempts']))) {
                                $this->parse('surveyLink_block', 'surveyLink');
                                $this->setVar('surveyNoLink_block', '');
                                $this->setVar('resumeLink_block', '');
                            } // else dont let them complete it again
                            else {
                                $this->setVar('surveyLink_block', '');
                                $this->parse('surveyNoLink_block', 'surveyNoLink');
                                $this->setVar('assessLink_block', '');
                                $this->setVar('resumeLink_block', '');
                                $this->setVar('SURVEY_FINISH_DATE', 'Completed');
                            }
                        }

                        /* Find out if you have recently completed an attempt - if so
                         * allow you to review what you just did
                         */
                        $this->setVar(
                            array(
                                'SURVEY_ATTEMPT_ID' => $attempt['attempt_id'],
                                'SURVEY_SECTION_ID' => $firstSection['section_id'],
                            )
                        );

                        $this->parse('reviewLink_block', 'reviewLink');
                    } else {
                        // show to everyone unless expired
                        if (strtotime($currentSurvey['finish']) < time()) {
                            $this->parse('surveyNoLink_block', 'surveyNoLink');
                            $this->setVar('surveyLink_block', '');
                            $this->setVar('resumeLink_block', '');
                            $this->setVar('reviewLink_block', '');
                        } else {
                            $this->parse('surveyLink_block', 'surveyLink');
                            $this->setVar('surveyNoLink_block', '');
                            $this->setVar('resumeLink_block', '');
                            $this->setVar('reviewLink_block', '');
                        }
                    }

                    /* This allows all users with write access to modify
                     */
                    $this->setVar('teacherLink_block', '');
                    if ($this->permission >= WRITE_ACCESS) {
                        $this->parse('teacherLink_block', 'teacherLink');
                    }
                    $this->parse('survey_block', 'survey', true);
                }
            }
        }

        return true; // everything went according to plan
    }
}
