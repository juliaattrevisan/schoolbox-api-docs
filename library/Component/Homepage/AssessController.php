<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_AssessController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'assess')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'assess', 'assess_block');
        $this->setBlock($templateHandle, 'createAssessment', 'createAssessment_block');
        $this->setBlock('assess', 'resultLink', 'resultLink_block');
        $this->setBlock('assess', 'teacherLink', 'teacherLink_block');
        $this->setBlock('assess', 'resumeLink', 'resumeLink_block');
        $this->setBlock('assess', 'assessLink', 'assessLink_block');
        $this->setBlock('assess', 'assessNoLink', 'assessNoLink_block');
	    $this->setBlock('assess', 'studentLink', 'studentLink_block');

        /* Remove resumeLink by default
        */
        $this->setVar('resumeLink_block', '');
	    $this->setVar('studentLink_block', '');

        $groupHandler = new Group();
        $assessHandler = new Assess_Main();
        $attemptHandler = new Assess_Attempt();
        $sectionHandler = new Assess_Section();
        $userHandler = new User();

        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('createAssessment_block', 'createAssessment');
        } else {
            $this->setVar('createAssessment_block', '');
        }

        $this->setVar('assess_block', '');

        $allAssess = $assessHandler->getList($this->getContext('id'));

        if (is_array($allAssess)) {
            $i = 0;

            foreach ($allAssess as $currentAssess) {

                /* Hide it from students if the start date hasn't passed yet
             */
                if (($this->permission >= WRITE_ACCESS) || $assessHandler->isOpen($currentAssess)) {
                    $createdTime = new Alaress_Date_Relative($currentAssess['created_at']);

                    $author = $userHandler->getByID($currentAssess['user_id']);

                    /* Note that Assess Descriptions can now contain html, so we decode them
                  * and remove the html from the short description in homepage components
                  */
                    $this->setVar(
                        array(
                            'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
                            'ASSESS_TITLE' => Scrub::Text($currentAssess['title']),
                            'ASSESS_START_DATE' => date('jS M Y', strtotime($currentAssess['start'])),
                            'ASSESS_FINISH_DATE' => date('jS M Y', strtotime($currentAssess['finish'])),
                            'ASSESS_DESCRIPTION' => Scrub::html($currentAssess['description']),
                            'ASSESS_CREATED' => $createdTime->getRelativeTime(),
                            'ASSESS_USER_ID' => $author['uid'],
                            'ASSESS_USER_FIRSTNAME' => $author['firstname'],
                            'ASSESS_USER_LASTNAME' => $author['lastname'],
                            'ON_OFF' => $i++ % 2 ? 'dark' : '',
                        )
                    );
                    if (isset($currentAssess['task_id'])) {
                        $this->setVar('ASSESS_FINISH_DATE', '');
                        $this->setVar('resultLink_block', '');
                    }
                    else {
                        $this->parse('resultLink_block', 'resultLink');
                    }

                    /* Check if you have completed an assessment already
                  *
                  * Set up Attempt vars
                  */
                    $attempt = $attemptHandler->getLatestForUser($currentAssess['assess_id'], $_SESSION['id']);
                    $attemptStartTime = strtotime($attempt['start']);
                    $attemptTimeRemaining = $attemptStartTime + $currentAssess['duration'] - time();
                    $attemptEndTime = $attemptStartTime + $currentAssess['duration'];

                    /* Tests can be undertaken if:
                  *
                  * 1. The assessment allows resit and the assessment hasn't expired
                  * 2. The assessment is one-time-only, and you haven't completed an attempt, and have time to do it, and have not yet attempted
                  * 3. The current time has passed the test's starting time
                  * 4. You aren't the owner of the test
                  */
                    if (is_array($attempt)) {
                        $numAttempts = $attemptHandler->userHasAttempted($currentAssess['assess_id'], $_SESSION['id']);

                        // If the last assess is not completed provide resume link
                        if ($attempt['finish'] == '0000-00-00 00:00:00' && ($attemptTimeRemaining < 0 || $currentAssess['duration'] == Assess_Main::UNLIMITED_DURATION)) {
                            $firstSection = $sectionHandler->getFirst($currentAssess['assess_id']);

                            $this->setVar(
                                array(
                                    'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
                                    'ASSESS_SECTION_ID' => $firstSection['section_id'],
                                    'ASSESS_FINISH_DATE' => 'In progress'
                                )
                            );

                            $this->parse('resumeLink_block', 'resumeLink');
                            $this->setVar('assessLink_block', '');
                            $this->setVar('assessNoLink_block', '');
                        } // allow to resit
                        else {
                            if (!(($currentAssess['max_attempts'] > 0) && ($numAttempts + 1 > $currentAssess['max_attempts']))) {
                                $this->parse('assessLink_block', 'assessLink');
                                $this->setVar('assessNoLink_block', '');
                                $this->setVar('resumeLink_block', '');
                            } // else dont let them complete it again
                            else {
                                $this->parse('assessNoLink_block', 'assessNoLink');
                                $this->setVar('assessLink_block', '');
                                $this->setVar('resumeLink_block', '');
                                $this->setVar('ASSESS_FINISH_DATE', 'Completed');
                            }
                        }


	                    // user has attempted, so show results page
	                    $firstSection = $sectionHandler->getFirst($currentAssess['assess_id']);
	                    $this->setVar(array(
		                    'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
		                    'ASSESS_SECTION_ID' => $firstSection['section_id'],
		                    'ASSESS_ATTEMPT_ID' => $attempt['attempt_id']
	                    ));
	                    $this->parse('studentLink_block', 'studentLink');

                    } else {
                        /* Do not show link if past finish date */
                        if ($assessHandler->isExpired($currentAssess['assess_id'])) {
                            $this->parse('assessNoLink_block', 'assessNoLink');
                            $this->setVar('assessLink_block', '');
                            $this->setVar('resumeLink_block', '');
                        } /* only show test link to owner and enrolled students */
                        else {

                            $enroled = false;
                            $categoryList = $groupHandler->involvedInCategoryList($_SESSION['id'], true);
                            /* See if you're in the Category first
                             */
                            if (is_array($categoryList)) {
                                foreach ($categoryList as $key => $value) {
                                    if ($value['id'] == $this->getContext('id')) {
                                        $enroled = true;
                                    }
                                }
                            }

                            if ($enroled || $currentAssess['user_id'] == $_SESSION['id']) {
                                $numAttempts = $attemptHandler->userHasAttempted(
                                    $currentAssess['assess_id'], $_SESSION['id']
                                );

                                if (($currentAssess['max_attempts'] > 0) && ($numAttempts + 1 > $currentAssess['max_attempts'])) {
                                    $this->setVar('assessLink_block', '');
                                    $this->parse('assessNoLink_block', 'assessNoLink');
                                    $this->setVar('resumeLink_block', '');
                                } else {
                                    $this->parse('assessLink_block', 'assessLink');
                                    $this->setVar('assessNoLink_block', '');
                                    $this->setVar('resumeLink_block', '');
                                }
                            } else {
                                $this->parse('assessNoLink_block', 'assessNoLink');
                                $this->setVar('assessLink_block', '');
                                $this->setVar('resumeLink_block', '');
                            }
                        }
                    }

                    /* Modified this check so that only the owner or a superuser can get
                  * these links which allow you to modify the assessment
                  */
                    if ($assessHandler->isOwner($currentAssess['assess_id']) || $groupHandler->getPermission(
                        $currentAssess['category_id']
                    ) >= WRITE_ACCESS
                    ) {
                        $this->parse('teacherLink_block', 'teacherLink');
                    } else {
                        $this->setVar('teacherLink_block', '');
                    }

                    $this->parse('assess_block', 'assess', true);
                }
            }
        }

        return true; // everything went according to plan
    }

}
