<?php
/**
 * Add a question to the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(126);
}

$questionBankHandler = new Survey_Question_Bank();

if (!empty($_POST)) {

    /* Don't add it if you haven't chozen a category
     */
    if (!is_numeric($_POST['question_category'])) {
        error(166);
    }

    $questionBankHandler->addQuestion(
		new Survey_Question(),
        $_POST['question_category'],
        $_POST['question_id']
    );

    SitePage::localRedir('/survey/questionbank/added/' . $_POST['question_id']);

} elseif (isset($this) && count($this->arg) > 0) {

    /** @var SitePage $this */

    $this->setAjax();

    /* Derive and render a category list
     */
    $assessId = $this->arg[0];

    $questionBankCategoryHandler = new Survey_Question_Category();

    $this->setFile(TEMPLATE_body, 'assess/questionbank/add.html');
    $this->setVar('ASSESS_KIND_LC', 'survey');
    $questionCategoryList = new Survey_Question_Category_List($this, $assessId, 0, $questionBankCategoryHandler->getPath());
    $questionCategoryList->render();
    $this->template->pparse('finaloutput', TEMPLATE_body);

} else {
    error(2);
}
