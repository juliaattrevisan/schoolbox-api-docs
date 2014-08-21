<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

/* Convert all answers from alpha index to numerical index
 */
$answerHandler = new Assess_Answer();

$allAnswers = $answerHandler->getAll();

foreach($allAnswers as $currentAnswer) {

    /* If an answer was provided, try to unserialize
     */
    if (strlen($currentAnswer['answer'])) {
        $result = unserialize($currentAnswer['answer']);

        /* Recompile options into this
         */
        $recompiledAnswer = array();

        if ($result && is_array($result)) {
            foreach($result as $currentResult) {
                $newResult = $currentResult;

                /* Translate only alphabetical values, translating A
                 * into 1, B into 2, etc. leaving numerical ones untouched
                 */
                if (ctype_alpha($newResult->value)) {
                    $newResult->value = ord($newResult->value)-64;
                }

                $recompiledAnswer[] = $newResult;
            }

            /* Update
             */
            $newAnswer = $currentAnswer;
            $newAnswer['answer'] = serialize($recompiledAnswer);

            /* Can use either handler to save to this table
             */
            $answerHandler->update($newAnswer);
        }
    }
}
