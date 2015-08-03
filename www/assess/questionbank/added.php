<?php
$this->setAjax();
$this->setFile(TEMPLATE_body, 'assess/questionbank/added.html');

$this->setVar(array(
	'QUESTION_ID' => $this->arg[0],
	'ASSESS_KIND_PLURAL' => 'quizzes'
));

$this->template->pparse('finaloutput', TEMPLATE_body);