<?php

/**
 * Add a question to the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$groupHandler = new Group();

if (!$groupHandler->isStaff()) {
    error(\Schoolbox\Error\Mapper::ERROR_PERM_TEST_MODIFY);
}

$questionBankHandler = new Survey_Question_Bank();

if (!empty($_POST)) {
    /* Don't add it if you haven't chozen a category
     */
    if (!is_numeric($_POST['question_category'])) {
        error(\Schoolbox\Error\Mapper::ERROR_QUESTION_BANK_CATEGORY);
    }

    $success = $questionBankHandler->addQuestion(
        new Survey_Question(),
        $_POST['question_category'],
        $_POST['question_id']
    );

    if ($success) {
        SitePage::localRedir('/survey/questionbank/added/' . $_POST['question_id']);
    } else {
        error(\Schoolbox\Error\Mapper::ERROR_PERM_RESOURCE_DELETE);
    }
} elseif (isset($this) && count($this->arg) > 0) {

    /** @var SitePage $this */

    $this->setAjax();

    /* Derive and render a category list
     */
    $assessId = $this->arg[0];

    $questionBankCategoryHandler = new Survey_Question_Category();

    $this->setFile(TEMPLATE_content, 'assess/questionbank/add.html');
    $this->setVar('ASSESS_KIND_LC', 'survey');
    $this->setVar('ASSESS_KIND_PLURAL', 'surveys');
    $questionCategoryList = new Survey_Question_Category_List($this, $assessId, 0, $questionBankCategoryHandler->getPath());
    $questionCategoryList->render();
    $this->injectGlobalVars();
    $this->template->pparse('finaloutput', TEMPLATE_content);
} else {
    error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
}
