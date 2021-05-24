<?php

$this->setAjax();
$this->setFile(TEMPLATE_content, 'assess/questionbank/added.html');

$this->setVar([
    'QUESTION_ID'        => $this->arg[0],
    'ASSESS_KIND_PLURAL' => 'quizzes',
]);

$this->injectGlobalVars();
$this->template->pparse('finaloutput', TEMPLATE_content);
