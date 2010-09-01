<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/model.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/assess/assess-factory.php');

$factory = new AssessFactory();

$type = $factory->getQuestionType($this->arg[0]);

$answer_file = "assess/answer/$type/create.html";

if (is_readable("../templates/core/$answer_file")) {
    $this->setFile(TEMPLATE_content, $answer_file);
    $this->template->pparse('finaloutput', TEMPLATE_content);
}
?>
