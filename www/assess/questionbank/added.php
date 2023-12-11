<?php

/* There's no real allegory to our legacy Question Bank - in learnosity era,
 * every item is in the itembank already. The only place you access that is
 * via learnosity quiz content authoring, but we don't really have the args
 * to find a particular assessment to which a person might be redirected.
 */

$this->pageNotFound();
//$this->setAjax();
//$this->setFile(TEMPLATE_content, 'assess/questionbank/added.html');
//
//$this->setVar([
//    'QUESTION_ID'        => $this->arg[0],
//    'ASSESS_KIND_PLURAL' => 'quizzes',
//]);
//
//$this->injectGlobalVars();
//$this->template->pparse('finaloutput', TEMPLATE_content);
