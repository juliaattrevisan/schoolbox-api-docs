<?php

/**
 * Remove a question from the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* There's no real allegory to our legacy Question Bank - in learnosity era,
 * every item is in the itembank already. The only place you access that is
 * via learnosity quiz content authoring, but we don't really have the args
 * to find a particular assessment to which a person might be redirected.
 */

$this->pageNotFound();

//$groupHandler = new Group();
//if (!$groupHandler->isStaff()) {
//    error(126);
//}
//
//$questionBankHandler = new Assess_Question_Bank();
//if (is_numeric($this->arg[0])) {
//    $questionBankHandler->update(array(
//            'question_bank_id' => $this->arg[0],
//            'deleted_at' => Alaress_Date_Format::now()->getDatabaseDate(),
//        ));
//}
//
//$this->backToTheFuture(1);
