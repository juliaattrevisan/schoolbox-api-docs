<?php
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/assess/Assess.php';
require_once SITE_root . '/library/assess/AssessAttempt.php';
require_once SITE_root . '/library/assess/AssessSection.php';

$this->setBlock($componentHandle, 'assess', 'assess_block');
$this->setBlock($componentHandle, 'createAssessment', 'createAssessment_block');
$this->setBlock('assess', 'teacherLink', 'teacherLink_block');
$this->setBlock('assess', 'resumeLink', 'resumeLink_block');
$this->setBlock('assess', 'assessLink', 'assessLink_block');
$this->setBlock('assess', 'assessNoLink', 'assessNoLink_block');

/* Remove resumeLink by default
 */
$this->setVar('resumeLink_block', '');

$groupHandler = new Group();
$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$sectionHandler = new AssessSection();
$allAssess = $assessHandler->getList($categoryID);

if ($groupHandler->isStaff()) {
    $this->parse('teacherLink_block', 'teacherLink');
    $this->parse('createAssessment_block', 'createAssessment');
}
else {
    $this->setVar('teacherLink_block', '');
    $this->setVar('createAssessment_block', '');
}

if (is_array($allAssess)) {
    $i=0;

    foreach($allAssess as $currentAssess) {

		/* Hide it from students if the start date hasn't passed yet
		 */
		if ($groupHandler->isStudent() && ((int)time() < (int)strtotime($currentAssess['start']))) {
			$this->setVar('assess_block', '');
		}
		else {
			$this->setVar(array(
				'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
				'ASSESS_TITLE' => $currentAssess['title'],
				'ASSESS_START_DATE' => date('jS M Y', strtotime($currentAssess['start'])),
				'ASSESS_FINISH_DATE' => date('jS M Y', strtotime($currentAssess['finish'])),
				'ASSESS_DESCRIPTION' => $currentAssess['description'],
				'ON_OFF' => $i%2 ? 'dark' : '',
			));

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
            if(is_array($attempt)) {
                // If the last assess is not completed provide resume link
                if ($attempt['finish'] == '0000-00-00 00:00:00' && ($attemptTimeRemaining < 0 || $currentAssess['duration'] == Assess::UNLIMITED_DURATION)) {
                    $firstSection = $sectionHandler->getFirst($currentAssess['assess_id']);

                    $this->setVar(array(
                        'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
                        'ASSESS_SECTION_ID' => $firstSection['section_id'],
                        'ASSESS_FINISH_DATE'     => 'In progress'
                    ));

                    $this->parse('resumeLink_block', 'resumeLink');
                    $this->setVar('assessLink_block', '');
                    $this->setVar('resumeLink_block', '');
                }
                // allow to resit
                else if ($currentAssess['allow_resit'] == 1) {
                    $this->parse('assessLink_block', 'assessLink');
                    $this->setVar('assessNoLink_block', '');
                    $this->setVar('resumeLink_block', '');
                }
                // else dont let them complete it again
                else {
                    $this->parse('assessNoLink_block', 'assessNoLink');
                    $this->setVar('assessLink_block', '');
                    $this->setVar('resumeLink_block', '');
                    $this->setVar('ASSESS_FINISH_DATE', 'Completed');
                }

			}
			else {

                /* only show test link to owner and enrolled students */
                if ($assessHandler->isExpired($currentAssess['assess_id'])) {
                    $this->parse('assessNoLink_block', 'assessNoLink');
					$this->setVar('assessLink_block', '');
                    $this->setVar('resumeLink_block', '');
				}
                else if($enroled || $currentAssess['user_id'] == $_SESSION['uid'] ) {
					$this->parse('assessLink_block', 'assessLink');
                    $this->setVar('assessNoLink_block', '');
                    $this->setVar('resumeLink_block', '');
				}
                else {
                    $this->parse('assessNoLink_block', 'assessNoLink');
					$this->setVar('assessLink_block', '');
                    $this->setVar('resumeLink_block', '');
                }
			}

			/* Modified this check so that only the owner or a superuser can get
			 * these links which allow you to modify the assessment
			 */
			if ($currentAssess['user_id'] == $_SESSION['uid'] || $groupHandler->isSuperuser()) {
				$this->parse('teacherLink_block', 'teacherLink');
			}
			else {
				$this->setVar('teacherLink_block', '');
			}

			$this->parse('assess_block', 'assess', true);
		}

        $i++;
    }
}
else {
    $this->setVar('assess_block', '');
}
?>
