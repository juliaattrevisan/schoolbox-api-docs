<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../library/Bootstrap.php';

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

            if (!strlen($newAnswer['deleted_at'])) {
                $newAnswer['deleted_at'] = 'NULL';
            }

            /* Can use either handler to save to this table
             */
            $answerHandler->update($newAnswer);
        }
    }
}

/* Convert all QuestionBank answers from alpha index to numerical index
 */
$questionBankHandler = new Assess_Question_Bank();

$allAnswers = $questionBankHandler->getAll();

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

            if (!strlen($newAnswer['deleted_at'])) {
                $newAnswer['deleted_at'] = 'NULL';
            }

            /* Can use either handler to save to this table
             */
            $questionBankHandler->update($newAnswer);
        }
    }
}


/* Convert all responses from alpha index to numerical index
 */
$responseHandler = new Assess_Response();

$allResponses = $responseHandler->getAll();

foreach($allResponses as $currentResponse) {

    /* If a response was provided, try to unserialize
     */
    if (strlen($currentResponse['response'])) {
        $result = unserialize($currentResponse['response']);

        /* Recompile options into this
         */
        $recompiledResponse = array();

        if ($result && is_array($result)) {
            foreach($result as $currentResult) {
                $newResult = $currentResult;

                /* Translate only alphabetical values, translating A
                 * into 1, B into 2, etc. leaving numerical ones untouched
                 */
                if (ctype_alpha($currentResult)) {
                    $newResult = ord($currentResult)-64;
                }

                $recompiledResponse[] = $newResult;
            }

            /* Update
             */
            $newResponse = $currentResponse;
            $newResponse['response'] = serialize($recompiledResponse);

            /* Can use either handler to save to this table
             */
            $responseHandler->update($newResponse);
        }
    }
}
