<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

class Component_Homepage_SurveyController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable()) {
            return $this->hasContextWriteAccess();
        }

        return true;
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

        $surveyHandler = new Survey_Main();
        $allSurveys    = $surveyHandler->getList($this->getContext()->getId());

        $this->setBlock($templateHandle, 'has_survey', 'has_survey_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if (!is_array($allSurveys)) {
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            }
            $this->setVar(
                [
                    'has_survey_block'  => '',
                    'empty_read_block'  => '',
                    'empty_write_block' => '',
                    'SOURCE_ID'         => $this->getSource()->getId(),
                ]
            );

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setBlock('has_survey', 'survey', 'survey_block');
            $this->setBlock('has_survey', 'createSurvey', 'createSurvey_block');
            $this->setBlock('survey', 'teacherLink', 'teacherLink_block');
            $this->setBlock('survey', 'surveyNoLink', 'surveyNoLink_block');
            $this->setBlock('survey', 'surveyLink', 'surveyLink_block');
            $this->setBlock('survey', 'resumeLink', 'resumeLink_block');
            $this->setBlock('survey', 'reviewLink', 'reviewLink_block');
            $this->setBlock('survey', 'authorLinkOpen', 'authorLinkOpen_block');
            $this->setBlock('survey', 'authorLinkClose', 'authorLinkClose_block');

            $this->setVar(
                [
                    'empty_state_block'  => '',
                    'resumeLink_block'   => '',
                    'reviewLink_block'   => '',
                    'createSurvey_block' => '',
                ]
            );

            $attemptHandler = new Survey_Attempt();
            $sectionHandler = new Survey_Section();

            if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                $this->parse('createSurvey_block', 'createSurvey');
            }

            $this->setVar('survey_block', '');

            if (is_array($allSurveys)) {
                $i = 0;

                foreach ($allSurveys as $currentSurvey) {
                    // Do not show the survey if you are a participant and the survey is not yet open
                    if (($this->getContentPermission() >= UserAccess::ACCESS_WRITE) || $surveyHandler->isOpen($currentSurvey)) {
                        $createdTime = new Alaress_Date_Format($currentSurvey['created_at'], 'Y-m-d H:i:s');

                        $author = UserMapper::getInstance()->findByIdWithFallback($currentSurvey['user_id']);

                        $this->setVar(
                            [
                                'SURVEY_ASSESS_ID'      => $currentSurvey['assess_id'],
                                'SURVEY_TITLE'          => Scrub::text($currentSurvey['title']),
                                'SURVEY_START_DATE'     => date('jS M Y', strtotime($currentSurvey['start'])),
                                'SURVEY_FINISH_DATE'    => 'Due ' . date('jS M Y', strtotime($currentSurvey['finish'])),
                                'SURVEY_DESCRIPTION'    => Scrub::html($currentSurvey['description']),
                                'SURVEY_CREATED'        => $createdTime->getRelativeTime(),
                                'SURVEY_USER_ID'        => $author->getId(),
                                'SURVEY_USER_FULLNAME'  => $author->getFullName(),
                            ]
                        );

                        /* Check if you have completed an assessment already
                         *
                         * Set up Attempt vars
                         */
                        $attempt              = $attemptHandler->getLatestForUser($currentSurvey['assess_id'], $_SESSION['id']);

                        /* Tests can be undertaken if:
                           *
                         * 1. The assessment allows resit and the assessment hasn't expired
                         * 2. The assessment is one-time-only, and you haven't completed an attempt, and have time to do it, and have not yet attempted
                         * 3. The current time has passed the test's starting time
                         * 4. You aren't the owner of the test
                            */
                        if (is_array($attempt)) {
                            $attemptStartTime     = strtotime($attempt['start']);
                            $attemptTimeRemaining = $attemptStartTime + $currentSurvey['duration'] - time();
                            $numAttempts  = $attemptHandler->userHasAttempted($currentSurvey['assess_id'], $_SESSION['id']);
                            $firstSection = $sectionHandler->getFirst($currentSurvey['assess_id']);

                            // If the last assess is not completed provide resume link
                            if (
                                !$attempt['finish']
                                && ($attemptTimeRemaining < 0
                                    || $currentSurvey['duration'] == Assess_Main::UNLIMITED_DURATION
                                )
                            ) {
                                $this->setVar(
                                    [
                                        'SURVEY_ASSESS_ID'   => $currentSurvey['assess_id'],
                                        'SURVEY_SECTION_ID'  => $firstSection['section_id'],
                                        'SURVEY_FINISH_DATE' => 'In progress',
                                    ]
                                );

                                $this->parse('resumeLink_block', 'resumeLink');
                                $this->setVar('surveyLink_block', '');
                                $this->setVar('surveyNoLink_block', '');
                            } elseif (!(($currentSurvey['max_attempts'] > 0) && ($numAttempts + 1 > $currentSurvey['max_attempts']))) {
                                // allow to resit
                                $this->parse('surveyLink_block', 'surveyLink');
                                $this->setVar('surveyNoLink_block', '');
                                $this->setVar('resumeLink_block', '');
                            } else {
                                // else dont let them complete it again
                                $this->setVar('surveyLink_block', '');
                                $this->parse('surveyNoLink_block', 'surveyNoLink');
                                $this->setVar('assessLink_block', '');
                                $this->setVar('resumeLink_block', '');
                                $this->setVar('SURVEY_FINISH_DATE', 'Completed');
                            }

                            /* Find out if you have recently completed an attempt - if so
                             * allow you to review what you just did
                             */
                            $this->setVar(
                                [
                                    'SURVEY_ATTEMPT_ID' => $attempt['attempt_id'],
                                    'SURVEY_SECTION_ID' => $firstSection['section_id'],
                                ]
                            );

                            $this->parse('reviewLink_block', 'reviewLink');
                        } else {
                            // show to everyone unless expired
                            if ((int)strtotime($currentSurvey['finish']) < time()) {
                                $this->parse('surveyNoLink_block', 'surveyNoLink');
                                $this->setVar(
                                    [
                                        'surveyLink_block' => '',
                                        'resumeLink_block' => '',
                                        'reviewLink_block' => '',
                                    ]
                                );
                            } else {
                                $this->parse('surveyLink_block', 'surveyLink');
                                $this->setVar(
                                    [
                                        'surveyNoLink_block' => '',
                                        'resumeLink_block'   => '',
                                        'reviewLink_block'   => '',
                                    ]
                                );
                            }
                        }

                        if ($author->isDeleted()) {
                            $this->setVar('authorLinkOpen_block', '');
                            $this->setVar('authorLinkClose_block', '');
                        } else {
                            $this->parse('authorLinkOpen_block', 'authorLinkOpen');
                            $this->parse('authorLinkClose_block', 'authorLinkClose');
                        }
                        /* This allows all users with write access to modify
                         */
                        $this->setVar('teacherLink_block', '');
                        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                            $this->parse('teacherLink_block', 'teacherLink');
                        }
                        $this->parse('survey_block', 'survey', true);
                    }
                }
            }

            $this->parse('has_survey_block', 'has_survey');
        }

        return true; // everything went according to plan
    }
}
