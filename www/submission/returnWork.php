<?php
$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setBlock(TEMPLATE_content, 'mark_type_percent', 'mark_type_percent_block');
$this->setBlock(TEMPLATE_content, 'mark_type_letter', 'mark_type_letter_block');
$this->setBlock(TEMPLATE_content, 'mark_type_raw', 'mark_type_raw_block');
$this->setBlock(TEMPLATE_content, 'mark_type_free', 'mark_type_free_block');
$this->setTitle('Return Student Work');
$this->setTab('submission');

if (is_numeric($this->arg[0]) && is_numeric($this->arg[1]) ) {
	$subHandler = new Submission;
	$subReturn = new SubmissionReturn;

	$userMapper = new \Schoolbox\UserMapper();
	$user = $userMapper->findById($this->arg[1]);

	$subReturn->securityCheck($this->arg[0]);

	$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
	$submissionBox = $submissionBoxMapper->findById($this->arg[0]);

	$this->setVar(array(
		'CATEGORY'  => \Scrub::text($submissionBox->getCategory()->getName()),
		'ASSNAME' => \Scrub::text($submissionBox->getName()),
		'BOX_ID' => $submissionBox->getId(),
		'NAME' => $user->formatName(),
		'SYN_ID' => $user->getExternalId(),
		'UID' => $user->getId(),
		'REDIRECT' => "/submission/viewSubmission/{$submissionBox->getId()}",
	));

	$marksheet = \Marksheet_Factory::build($submissionBox->getMarkType()->getSlug());
	$mark = $marksheet->retrieveMark($user->getId(), $submissionBox->getId(), false, false);

	$this->setVar('MARK_TYPE_COMMENT', $marksheet->getComment());

	// blockify and clear all the marktypes
	foreach ($subHandler->getMarkTypes(true) as $type) {
		$this->setBlock(TEMPLATE_content, "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");
		$this->setVar("mark_type_{$type['slug']}_block", '');
	}

	/* Render rubrics
	 */
	$renderer = new \Rubric\Renderer\Horizontal($this, $submissionBox, $user);
	$renderer->render();

	/* Parse out everything
	 */
    $slug = $marksheet->getSlug();
    $this->parse("mark_type_{$slug}_block", "mark_type_{$slug}");
}
else {
    error(2);
}
