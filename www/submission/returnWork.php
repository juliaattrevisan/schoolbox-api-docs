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

    $subbox = $subHandler->getByID($this->arg[0]);

	$this->setVar(array(
		'CATEGORY'  => \Scrub::text($subbox['categoryName']),
		'ASSNAME' => \Scrub::text($subbox['name']),
		'BOX_ID' => $subbox['id'],
		'NAME' => $user->formatName(),
		'SYN_ID' => $user->getExternalId(),
		'UID' => $user->getId(),
	));

	$marksheet = \Marksheet_Factory::build($subbox['mark_type_slug']);
	$mark = $marksheet->retrieveMark($user->getId(), $subbox['id'], false, false);

	$this->setVar('MARK_TYPE_COMMENT', $marksheet->getComment());

	// blockify and clear all the marktypes
	foreach ($subHandler->getMarkTypes(true) as $type) {
		$this->setBlock(TEMPLATE_content, "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");
		$this->setVar("mark_type_{$type['slug']}_block", '');
	}

	/* Render rubrics
	 */
	$renderer = new \Rubric\Renderer($this, $subbox, $user);
	$renderer->render();

	/* Parse out everything
	 */
    $slug = $marksheet->getSlug();
    $this->parse("mark_type_{$slug}_block", "mark_type_{$slug}");
}
else {
    error(2);
}
