<?php
$this->setBlock($componentHandle, 'survey', 'survey_block');
$this->setBlock($componentHandle, 'createSurvey', 'createSurvey_block');
$this->setBlock('survey', 'teacherLink', 'teacherLink_block');
$this->setBlock('survey', 'surveyNoLink', 'surveyNoLink_block');
$this->setBlock('survey', 'surveyLink', 'surveyLink_block');
$this->setBlock('survey', 'resumeLink', 'resumeLink_block');

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

$surveyHandler = new Survey_Main();
$attemptHandler = new Survey_Attempt();
$sectionHandler = new Survey_Section();
$userHandler = new User();

$allSurveys = $surveyHandler->getList($categoryID);

if ($permission >= WRITE_ACCESS) {
    $this->parse('createSurvey_block', 'createSurvey');
}
else {
    $this->setVar('createSurvey_block', '');
}

if (is_array($allSurveys)) {
    $i=0;

    foreach($allSurveys as $currentSurvey) {

		/* Do not show the survey if you are a participant and the survey is not yet open
		*/
		if (($permission >= WRITE_ACCESS) || $surveyHandler->isOpen($currentSurvey)) {
			$createdTime = new Alaress_Date_Relative($currentSurvey['created_at']);

			$author = $userHandler->getByID($currentSurvey['user_id']);

			$this->setVar(array(
				'SURVEY_ASSESS_ID' => $currentSurvey['assess_id'],
				'SURVEY_TITLE' => $currentSurvey['title'],
				'SURVEY_START_DATE' => date('jS M Y', strtotime($currentSurvey['start'])),
				'SURVEY_FINISH_DATE' => date('jS M Y', strtotime($currentSurvey['finish'])),
				'SURVEY_DESCRIPTION' => Scrub::html(html_entity_decode($currentSurvey['description'])),
				'SURVEY_CREATED' => $createdTime->getRelativeTime(),
				'SURVEY_USER_ID' => $author['uid'],
				'SURVEY_USER_FIRSTNAME' => $author['firstname'],
				'SURVEY_USER_LASTNAME' => $author['lastname'],
				'ON_OFF' => $i++%2 ? 'dark' : '',
			));

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
			if (is_array($attempt)) {
				// If the last assess is not completed provide resume link
				if ($attempt['finish'] == '0000-00-00 00:00:00' && ($attemptTimeRemaining < 0 || $currentSurvey['duration'] == Assess_Main::UNLIMITED_DURATION)) {
					$firstSection = $sectionHandler->getFirst($currentSurvey['assess_id']);

					$this->setVar(array(
						'SURVEY_ASSESS_ID' => $currentSurvey['assess_id'],
						'SURVEY_SECTION_ID' => $firstSection['section_id'],
						'SURVEY_FINISH_DATE'  => 'In progress'
					));

					$this->parse('resumeLink_block', 'resumeLink');
					$this->setVar('surveyLink_block', '');
					$this->setVar('surveyNoLink_block', '');
				}
				// allow to resit
				else if ($currentSurvey['allow_resit'] == 1) {
					$this->parse('surveyLink_block', 'surveyLink');
					$this->setVar('surveyNoLink_block', '');
					$this->setVar('resumeLink_block', '');
				}
				// else dont let them complete it again
				else {
					$this->setVar('surveyLink_block', '');
					$this->parse('surveyNoLink_block', 'surveyNoLink');
					$this->setVar('assessLink_block', '');
					$this->setVar('resumeLink_block', '');
					$this->setVar('SURVEY_FINISH_DATE', 'Completed');
				}
			}
			else {

				/* show to everyone unless expired */
				if (strtotime($currentSurvey['finish']) < mktime() ) {
					$this->parse('surveyNoLink_block', 'surveyNoLink');
					$this->setVar('surveyLink_block', '');
					$this->setVar('resumeLink_block', '');
				}
				else {
					$this->parse('surveyLink_block', 'surveyLink');
					$this->setVar('surveyNoLink_block', '');
					$this->setVar('resumeLink_block', '');
				}
			}

			/* Modified this check so that only the owner or a superuser can get
			 * these links which allow you to modify the assessment
			 */
			if ($surveyHandler->isOwner($currentSurvey['assess_id']) || $groupHandler->getPermission($currentSurvey['category_id']) >= WRITE_ACCESS) {
				$this->parse('teacherLink_block', 'teacherLink');
			}
			else {
				$this->setVar('teacherLink_block', '');
			}

			$this->parse('survey_block', 'survey', true);
		}
    }
}
else {
    $this->setVar('survey_block', '');
}
