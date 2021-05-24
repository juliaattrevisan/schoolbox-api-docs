<?php

/**
 * Remove a question from the Question Bank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$groupHandler = new Group();
if (!$groupHandler->isStaff()) {
    error(126);
}

$questionBankHandler = new Assess_Question_Bank();
if (is_numeric($this->arg[0])) {
    $questionBankHandler->update(array(
            'question_bank_id' => $this->arg[0],
            'deleted_at' => 'NOW()',
        ));
}

$this->backToTheFuture(1);
