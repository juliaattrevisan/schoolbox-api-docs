<?php
require_once SITE_root . '/library/survey/Survey.php';
require_once SITE_root . '/library/survey/SurveyAttempt.php';
require_once SITE_root . '/library/survey/SurveySection.php';

$this->setBlock($componentHandle, 'survey', 'survey_block');
$this->setBlock($componentHandle, 'createSurvey', 'createSurvey_block');
$this->setBlock('survey', 'teacherLink', 'teacherLink_block');
$this->setBlock('survey', 'studentLink', 'studentLink_block');
$this->setBlock('survey', 'resultsLink', 'resultsLink_block');
$this->setBlock('survey', 'resumeLink', 'resumeLink_block');
$this->setBlock('survey', 'hasCompleted', 'hasCompleted_block');

/* Remove resumeLink by default
 */
$this->setVar('resumeLink_block', '');

$surveyHandler = new Survey();
$attemptHandler = new SurveyAttempt();
$sectionHandler = new SurveySection();
$allSurveys = $surveyHandler->getList($categoryID);

if ($groupHandler->isStaff()) {
    $this->setVar('studentLink_block', '');
	$this->setVar('resultsLink_block', '');
    $this->parse('teacherLink_block', 'teacherLink');
    $this->parse('createSurvey_block', 'createSurvey');
}
else {
    $this->setVar('teacherLink_block', '');
    $this->parse('studentLink_block', 'studentLink');
    $this->setVar('createSurvey_block', '');
}

if (is_array($allSurveys)) {
    $i=0;

    foreach($allSurveys as $currentSurvey) {
        $this->setVar(array(
            'SURVEY_ASSESS_ID' => $currentSurvey['assess_id'],
            'SURVEY_TITLE' => $currentSurvey['title'],
            'SURVEY_START_DATE' => date('jS F Y, g:i:sa', strtotime($currentSurvey['start'])),
            'SURVEY_FINISH_DATE' => date('jS F Y, g:i:sa', strtotime($currentSurvey['finish'])),
            'SURVEY_DESCRIPTION' => $currentSurvey['description'],
            'ON_OFF' => $i%2 ? 'dark' : '',
        ));

		/* If at least one attempt was reviewed, provide link to Due
		 * Work screen, where the attempts are all listed
		 */
		if (!$groupHandler->isStaff()) {
			$allAttempts = $attemptHandler->getListForUser($currentSurvey['assess_id'], $_SESSION['id']);

			if (is_array($allAttempts) && count($allAttempts)) {
				$assessAttempted = false;

				foreach($allAttempts as $currentAttempt) {
					if ($currentAttempt['finish'] == '0000-00-00 00:00:00') {
						$firstSection = $sectionHandler->getFirst($currentSurvey['assess_id']);

						$this->setVar(array(
							'SURVEY_ASSESS_ID' => $currentSurvey['assess_id'],
							'SURVEY_SECTION_ID' => $firstSection['section_id'],
						));

						$this->parse('resumeLink_block', 'resumeLink');
						$this->setVar('hasCompleted_block', '');
					}
					else {
						$this->setVar('resumeLink_block', '');
						$this->parse('hasCompleted_block', 'hasCompleted');
					}

					if ($assessAttempted == false) {
						if (($currentAttempt['reviewed_at'] != null)) {
							$this->parse('resultsLink_block', 'resultsLink');

							$assessAttempted = true;
						}
						else {
							$this->setVar('resultsLink_block', '');
						}
					}
				}
			}
			else {
				$this->setVar('resultsLink_block', '');
				$this->setVar('hasCompleted_block', '');
			}
		}
		else {
			$this->setVar('hasCompleted_block', '');
		}

        /* Check if you have completed an assessment already
         *
         * Set up Attempt vars
         */
        $attempt = $attemptHandler->getLatestForUser($currentSurvey['assess_id'], $_SESSION['id']);
        $attemptStartTime = strtotime($attempt['start']);
        $attemptTimeRemaining = $attemptStartTime + $currentSurvey['duration'] - time();
        $attemptEndTime = $attemptStartTime + $currentSurvey['duration'];

        /* Tests can be undertaken if:
         *
         * 1. The assessment allows resit and the assessment hasn't expired
         * 2. The assessment is one-time-only, and you haven't completed an attempt, and have time to do it, and have not yet attempted
         * 3. The current time has passed the test's starting time
		 * 4. You aren't the owner of the test
         */
        if ($currentSurvey['allow_resit'] == 1) {
			/* Disabled: doing so will allow teachers to give their test a "dry run" and
 			 * they can delete their attempts afterwards
			 *
			if ($surveyHandler->isOwner($currentSurvey['assess_id'])) {
				$this->setVar('studentLink_block', '');
			}
			 */
            if (($currentSurvey['duration'] == Survey::UNLIMITED_DURATION) || (strtotime($currentSurvey['finish']) > time())) {
				$this->parse('studentLink_block', 'studentLink');
            }
            else {
                $this->setVar('studentLink_block', '');
            }
        }
        else {
            if ($attemptHandler->isComplete($currentSurvey['assess_id'])) {
                $this->setVar('studentLink_block', '');
            }
            else if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0)) {
                $this->setVar('studentLink_block', '');
            }
            else if ($surveyHandler->isExpired($currentSurvey['assess_id'])) {
                $this->setVar('studentLink_block', '');
            }
            else if (((int)time() - (int)strtotime($currentSurvey['start'])) < 0) {
                $this->setVar('studentLink_block', '');
            }

			/* Disabled: doing so will allow teachers to give their test a "dry run" and
 			 * they can delete their attempts afterwards
			 *
			else if ($surveyHandler->isOwner($currentSurvey['assess_id'])) {
				$this->setVar('studentLink_block', '');
			}
			*/
            else {
                $this->parse('studentLink_block', 'studentLink');
            }
        }

        /* Modified this check so that only the owner or a superuser can get
		 * these links which allow you to modify the assessment
         */
        if ($surveyHandler->isOwner($currentSurvey['assess_id']) || $groupHandler->isSuperuser()) {
            $this->parse('teacherLink_block', 'teacherLink');
        }
        else {
            $this->setVar('teacherLink_block', '');
        }

        $this->parse('survey_block', 'survey', true);

        $i++;
    }
}
else {
    $this->setVar('survey_block', '');
}
?>