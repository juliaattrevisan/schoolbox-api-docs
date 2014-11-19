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

$questionBankHandler = new Assess_Question_Bank();

if (!empty($_POST)) {

    /* Don't add it if you haven't chozen a category
     */
    if (!is_numeric($_POST['question_category'])) {
        error(166);
    }

    $questionBankHandler->addQuestion(
		new Assess_Question(),
        $_POST['question_category'],
        $_POST['type_id'],
        $_POST['question_id'],
        $_POST['answer_id'],
        $_POST['marks']
    );

    SitePage::localRedir('/assess/questionbank/added/' . $_POST['question_id']);
} else {
    error(2);
}