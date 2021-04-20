<?php
/**
 * Add a question to the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Learning\Assessment\Redirect;

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

if (!empty($_POST)) {

    /* Don't add it if you haven't chozen a category
     */
    if (!is_numeric($_POST['question_category'])) {
        error(166);
    }

    $questionBankHandler = new Assess_Question_Bank();

    $success = $questionBankHandler->addQuestion(
        new Assess_Question(),
        $_POST['question_category'],
        $_POST['question_id']
    );

    if ($success) {

        $returnUrl = $_POST[Redirect::RETURN_URL] != '' ? "?".Redirect::RETURN_URL."={$_POST[Redirect::RETURN_URL]}" : "";

        SitePage::localRedir('/assess/questionbank/added/' . $_POST['question_id'] . $returnUrl);
    } else {
        error(8);
    }

} elseif (isset($this) && count($this->arg) > 0) {

    /** @var SitePage $this */

    $this->setAjax();

    /* Derive and render a category list
     */
    $assessId = $this->arg[0];

    $questionBankCategoryHandler = new Assess_Question_Category();

    $this->setFile(TEMPLATE_content, 'assess/questionbank/add.html');
    $this->setVar('ASSESS_KIND_LC', 'assess');
    $this->setVar('ASSESS_KIND_PLURAL', 'quizzes');
    $questionCategoryList = new Assess_Question_Category_List($this, $assessId, 0, $questionBankCategoryHandler->getPath());
    $questionCategoryList->render();
    $this->injectGlobalVars();
    $this->template->pparse('finaloutput', TEMPLATE_content);

} else {
    error(2);
}
