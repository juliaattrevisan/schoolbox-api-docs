<?php
require_once SITE_root . '/library/group.php';

$this->setBlock($componentHandle, 'assess', 'assess_block');
$this->setBlock($componentHandle, 'createAssessment', 'createAssessment_block');
$this->setBlock('assess', 'teacherLink', 'teacherLink_block');
$this->setBlock('assess', 'resumeLink', 'resumeLink_block');
$this->setBlock('assess', 'assessLink', 'assessLink_block');
$this->setBlock('assess', 'assessNoLink', 'assessNoLink_block');

/* Tick for checkbox which controls whether to show the Posted By details
 */
if ($config['compactMode']) {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => 'compact-mode',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
	));
}
else {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => '',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
	));
}

/* Remove resumeLink by default
 */
$this->setVar('resumeLink_block', '');

$groupHandler = new Group();
$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$sectionHandler = new Assess_Section();
$userHandler = new User();

if ($permission >= WRITE_ACCESS) {
    $this->parse('createAssessment_block', 'createAssessment');
}
else {
    $this->setVar('createAssessment_block', '');
}

$allAssess = $assessHandler->getList($categoryID);

if (is_array($allAssess)) {
    $i=0;

    foreach($allAssess as $currentAssess) {

		/* Hide it from students if the start date hasn't passed yet
		 */
		if (($permission < WRITE_ACCESS) && !$assessHandler->isOpen($currentAssess['assess_id'])) {
			$this->setVar('assess_block', '');
		}
		else {
			$createdTime = new Alaress_Date_Relative($currentAssess['created_at']);

			$author = $userHandler->getByID($currentAssess['user_id']);

			/* Note that Assess Descriptions can now contain html, so we decode them
			 * and remove the html from the short description in homepage components
			 */
			$this->setVar(array(
				'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
				'ASSESS_TITLE' => $currentAssess['title'],
				'ASSESS_START_DATE' => date('jS M Y', strtotime($currentAssess['start'])),
				'ASSESS_FINISH_DATE' => date('jS M Y', strtotime($currentAssess['finish'])),
				'ASSESS_DESCRIPTION' => Scrub::html(html_entity_decode($currentAssess['description'])),
				'ASSESS_CREATED' => $createdTime->getRelativeTime(),
				'ASSESS_USER_ID' => $author['uid'],
				'ASSESS_USER_FIRSTNAME' => $author['firstname'],
				'ASSESS_USER_LASTNAME' => $author['lastname'],
				'ON_OFF' => $i++%2 ? 'dark' : '',
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
            if (is_array($attempt)) {
                // If the last assess is not completed provide resume link
                if ($attempt['finish'] == '0000-00-00 00:00:00' && ($attemptTimeRemaining < 0 || $currentAssess['duration'] == Assess_Main::UNLIMITED_DURATION)) {
                    $firstSection = $sectionHandler->getFirst($currentAssess['assess_id']);

                    $this->setVar(array(
                        'ASSESS_ASSESS_ID' => $currentAssess['assess_id'],
                        'ASSESS_SECTION_ID' => $firstSection['section_id'],
                        'ASSESS_FINISH_DATE'     => 'In progress'
                    ));

                    $this->parse('resumeLink_block', 'resumeLink');
                    $this->setVar('assessLink_block', '');
                    $this->setVar('assessNoLink_block', '');
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

                /* Do not show link if past finish date */
                if (strtotime($currentAssess['finish']) < mktime()) {
                    $this->parse('assessNoLink_block', 'assessNoLink');
					$this->setVar('assessLink_block', '');
                    $this->setVar('resumeLink_block', '');
				}
                /* only show test link to owner and enrolled students */
                else if($enroled || $currentAssess['user_id'] == $_SESSION['id'] ) {
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
			if ($assessHandler->isOwner($currentAssess['assess_id']) || $groupHandler->getPermission($currentAssess['category_id']) >= WRITE_ACCESS) {
				$this->parse('teacherLink_block', 'teacherLink');
			}
			else {
				$this->setVar('teacherLink_block', '');
			}

			$this->parse('assess_block', 'assess', true);
		}
    }
}
else {
    $this->setVar('assess_block', '');
}
