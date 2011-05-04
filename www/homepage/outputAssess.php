<?php
require_once SITE_root.'/library/assess/assess.php';
require_once SITE_root.'/library/assess/assess-attempt.php';
require_once SITE_root.'/library/assess/assess-section.php';

$this->setBlock($componentHandle, 'assess', 'assess_block');
$this->setBlock($componentHandle, 'createAssessment', 'createAssessment_block');
$this->setBlock('assess', 'teacherLink', 'teacherLink_block');
$this->setBlock('assess', 'studentLink', 'studentLink_block');
$this->setBlock('assess', 'resultsLink', 'resultsLink_block');
$this->setBlock('assess', 'resumeLink', 'resumeLink_block');
$this->setBlock('assess', 'hasCompleted', 'hasCompleted_block');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$sectionHandler = new AssessSection();
$allAssess = $assessHandler->getList($categoryID);

if ($groupHandler->isStaff()) {
    $this->setVar('studentLink_block', '');
	$this->setVar('resultsLink_block', '');
    $this->parse('teacherLink_block', 'teacherLink');
    $this->parse('createAssessment_block', 'createAssessment');
}
else {
    $this->setVar('teacherLink_block', '');
    $this->parse('studentLink_block', 'studentLink');
    $this->setVar('createAssessment_block', '');
}

if (is_array($allAssess)) {
    $i=0;

    foreach($allAssess as $currentAssess) {
        $this->setVar(array(
            'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
            'ASSESS_TITLE' => $currentAssess['title'],
            'ASSESS_START_DATE' => date('jS F Y, g:i:sa', strtotime($currentAssess['start'])),
            'ASSESS_FINISH_DATE' => date('jS F Y, g:i:sa', strtotime($currentAssess['finish'])),
            'ASSESS_DESCRIPTION' => $currentAssess['description'],
            'ON_OFF' => $i%2 ? 'dark' : '',
        ));

		/* If at least one attempt was reviewed, provide link to Due
		 * Work screen, where the attempts are all listed
		 */
		if (!$groupHandler->isStaff()) {
			$allAttempts = $attemptHandler->getList($currentAssess['assess_id']);

			if (is_array($allAttempts) && count($allAttempts)) {
				$assessAttempted = false;

				foreach($allAttempts as $currentAttempt) {
					if ($currentAttempt['finish'] == '0000-00-00 00:00:00') {
						$firstSection = $sectionHandler->getFirst($currentAssess['assess_id']);

						$this->setVar(array(
							'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
							'ASSESS_SECTION_ID' => $firstSection['section_id'],
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
        if ($currentAssess['allow_resit'] == 1) {
			if ($assessHandler->isOwner($currentAssess['assess_id'])) {
				$this->setVar('studentLink_block', '');
			}
            else if (($currentAssess['duration'] == Assess::UNLIMITED_DURATION) || (strtotime($currentAssess['finish']) > time())) {
				$this->parse('studentLink_block', 'studentLink');
            }
            else {
                $this->setVar('studentLink_block', '');
            }
        }
        else {
            if ($attemptHandler->isComplete($currentAssess['assess_id'])) {
                $this->setVar('studentLink_block', '');
            }
            else if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0)) {
                $this->setVar('studentLink_block', '');
            }
            else if ($assessHandler->isExpired($currentAssess['assess_id'])) {
                $this->setVar('studentLink_block', '');
            }
            else if (((int)time() - (int)strtotime($currentAssess['start'])) < 0) {
                $this->setVar('studentLink_block', '');
            }
			else if ($assessHandler->isOwner($currentAssess['assess_id'])) {
				$this->setVar('studentLink_block', '');
			}
            else {
                $this->parse('studentLink_block', 'studentLink');
            }
        }

        /* Modified this check so that only the owner can get these links
         */
        if ($assessHandler->isOwner($currentAssess['assess_id'])) {
            $this->parse('teacherLink_block', 'teacherLink');
        }
        else {
            $this->setVar('teacherLink_block', '');
        }

        $this->parse('assess_block', 'assess', true);

        $i++;
    }
}
else {
    $this->setVar('assess_block', '');
}
?>