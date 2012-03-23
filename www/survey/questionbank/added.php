<?php
$this->setFile(TEMPLATE_content, 'survey/questionbank/added.html');

$this->setVar(array(
	'QUESTION_ID' => $this->arg[0],
));

